using System.Collections.Generic;
using UnityEngine;

namespace WastelandRush
{
    public class Zombie : MonoBehaviour
    {
        public enum Kind { Walker, Runner, Brute }

        public static readonly List<Zombie> Alive = new List<Zombie>();

        public Kind kind;
        public bool IsDead { get; private set; }

        Rigidbody rb;
        CapsuleCollider col;
        Transform model;
        float speed, attackCooldown, animTime, facing = -1f;
        int coins;

        static readonly Color[] Clothes =
        {
            new Color(0.35f, 0.3f, 0.45f), new Color(0.5f, 0.4f, 0.3f),
            new Color(0.3f, 0.4f, 0.5f), new Color(0.55f, 0.25f, 0.25f), new Color(0.4f, 0.4f, 0.4f),
        };

        public static Zombie Spawn(Transform parent, Vector3 pos, Kind kind)
        {
            var go = new GameObject("Zombie_" + kind);
            go.transform.SetParent(parent, false);
            go.transform.position = pos;
            var z = go.AddComponent<Zombie>();
            z.Build(kind);
            return z;
        }

        void Build(Kind k)
        {
            kind = k;
            transform.localScale = Vector3.one * (k == Kind.Brute ? 1.45f : k == Kind.Runner ? 0.92f : 1f);

            col = gameObject.AddComponent<CapsuleCollider>();
            col.height = 1.8f;
            col.radius = 0.35f;
            col.center = new Vector3(0f, 0.9f, 0f);

            rb = gameObject.AddComponent<Rigidbody>();
            rb.mass = k == Kind.Brute ? 220f : k == Kind.Runner ? 55f : 70f;
            rb.constraints = RigidbodyConstraints.FreezePositionZ | RigidbodyConstraints.FreezeRotation;

            Color skin = k == Kind.Runner ? new Color(0.6f, 0.72f, 0.38f)
                       : k == Kind.Brute ? new Color(0.36f, 0.5f, 0.3f)
                       : new Color(0.47f, 0.64f, 0.42f);
            Color cloth = Clothes[Random.Range(0, Clothes.Length)];
            Color pants = new Color(0.2f, 0.22f, 0.3f);

            model = new GameObject("Model").transform;
            model.SetParent(transform, false);
            Util.Prim(PrimitiveType.Cube, model, new Vector3(0f, 0.3f, -0.13f), new Vector3(0.2f, 0.6f, 0.2f), pants);
            Util.Prim(PrimitiveType.Cube, model, new Vector3(0f, 0.3f, 0.13f), new Vector3(0.2f, 0.6f, 0.2f), pants);
            Util.Prim(PrimitiveType.Capsule, model, new Vector3(0f, 0.95f, 0f), new Vector3(0.6f, 0.42f, 0.5f), cloth);
            Util.Prim(PrimitiveType.Sphere, model, new Vector3(-0.08f, 1.55f, 0f), Vector3.one * 0.42f, skin);
            Util.Prim(PrimitiveType.Sphere, model, new Vector3(-0.26f, 1.6f, -0.09f), Vector3.one * 0.07f, Color.red);
            Util.Prim(PrimitiveType.Sphere, model, new Vector3(-0.26f, 1.6f, 0.09f), Vector3.one * 0.07f, Color.red);
            // arms reach forward (toward -X, where the car comes from)
            Util.Prim(PrimitiveType.Cube, model, new Vector3(-0.38f, 1.18f, -0.24f), new Vector3(0.62f, 0.13f, 0.13f), skin);
            Util.Prim(PrimitiveType.Cube, model, new Vector3(-0.38f, 1.12f, 0.24f), new Vector3(0.62f, 0.13f, 0.13f), skin);

            Util.SetLayerRecursive(gameObject, Layers.Props);

            speed = k == Kind.Runner ? Random.Range(3f, 4.5f) : k == Kind.Brute ? Random.Range(0.8f, 1.2f) : Random.Range(1f, 2f);
            coins = k == Kind.Brute ? 6 : k == Kind.Runner ? 3 : 2;
            animTime = Random.value * 10f;
            Alive.Add(this);
        }

        void OnDestroy() => Alive.Remove(this);

        void FixedUpdate()
        {
            if (IsDead) return;
            var gm = GameManager.Instance;
            var car = gm != null ? gm.Car : null;
            if (car == null) return;

            float dx = car.transform.position.x - transform.position.x;
            if (Mathf.Abs(dx) < 35f && Mathf.Abs(dx) > 0.6f)
            {
                facing = Mathf.Sign(dx);
                var v = rb.GetVel();
                v.x = Mathf.MoveTowards(v.x, facing * speed, 10f * Time.fixedDeltaTime);
                rb.SetVel(v);
            }
            if (attackCooldown > 0f) attackCooldown -= Time.fixedDeltaTime;
            if (transform.position.y < -40f) Destroy(gameObject);
        }

        void Update()
        {
            if (IsDead || model == null) return;
            animTime += Time.deltaTime * speed * 2.5f;
            model.localRotation = Quaternion.Euler(0f, facing > 0f ? 180f : 0f, Mathf.Sin(animTime) * 8f);
        }

        void OnCollisionEnter(Collision c) => HandleCar(c);
        void OnCollisionStay(Collision c) => HandleCar(c);

        void HandleCar(Collision c)
        {
            if (IsDead) return;
            var car = c.collider.GetComponentInParent<CarController>();
            if (car == null) return;

            float killSpeed = kind == Kind.Brute ? 9f : 4.5f;
            if (c.relativeVelocity.magnitude > killSpeed)
            {
                car.Damage(kind == Kind.Brute ? 5f : 1f);
                Kill(car.Velocity, car);
            }
            else if (attackCooldown <= 0f)
            {
                // The car is stuck in the horde: zombies claw at it.
                car.Damage(kind == Kind.Brute ? 8f : 4f);
                attackCooldown = 0.6f;
            }
        }

        public void Kill(Vector3 hitVelocity, CarController car)
        {
            if (IsDead) return;
            IsDead = true;
            Alive.Remove(this);

            if (car != null) car.IgnoreCollisionsWith(col);
            rb.constraints = RigidbodyConstraints.None;
            rb.SetVel(hitVelocity * 0.9f + Vector3.up * Random.Range(4f, 8f) + new Vector3(0f, 0f, Random.Range(-4f, 4f)));
            rb.angularVelocity = Random.insideUnitSphere * 12f;

            foreach (var r in GetComponentsInChildren<Renderer>())
                r.sharedMaterial = Util.Mat(Color.Lerp(r.sharedMaterial.color, new Color(0.45f, 0.05f, 0.05f), 0.5f));

            // Gibs
            for (int i = 0; i < 4; i++)
            {
                var g = Util.Prim(PrimitiveType.Cube, transform.parent, transform.position + Vector3.up * 1.2f,
                    Vector3.one * Random.Range(0.12f, 0.25f), new Color(0.55f, 0.05f, 0.05f), true);
                g.layer = Layers.Props;
                var grb = g.AddComponent<Rigidbody>();
                grb.mass = 2f;
                grb.SetVel(hitVelocity * 0.5f + Random.insideUnitSphere * 6f + Vector3.up * 4f);
                if (car != null) car.IgnoreCollisionsWith(g.GetComponent<Collider>());
                Destroy(g, 2.5f);
            }

            if (GameManager.Instance != null) GameManager.Instance.RegisterKill(coins);
            Destroy(gameObject, 4f);
        }
    }
}
