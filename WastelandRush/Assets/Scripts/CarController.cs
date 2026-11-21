using System.Collections.Generic;
using UnityEngine;

namespace WastelandRush
{
    /// <summary>
    /// Arcade side-scrolling car built from primitives. Uses raycast suspension
    /// (no WheelColliders) so it drives smoothly over the procedural terrain mesh.
    /// The car faces +X; the game plays on the X/Y plane (Z is frozen).
    /// </summary>
    public class CarController : MonoBehaviour
    {
        public RunStats stats;

        public float Fuel { get; private set; }
        public float Hp { get; private set; }
        public float Boost { get; private set; }
        public bool Boosting { get; private set; }
        public bool Dead { get; private set; }
        public bool Grounded => groundedWheels > 0;
        public float ForwardSpeed => rb != null ? Vector3.Dot(rb.GetVel(), transform.right) : 0f;
        public float Speed => rb != null ? rb.GetVel().magnitude : 0f;
        public Vector3 Velocity => rb != null ? rb.GetVel() : Vector3.zero;

        const float RestLength = 0.5f;
        const float WheelRadius = 0.45f;
        static readonly Vector3[] WheelAnchors = { new Vector3(-1.25f, -0.1f, 0f), new Vector3(1.3f, -0.1f, 0f) };

        Rigidbody rb;
        readonly List<Transform>[] wheelVisuals = { new List<Transform>(), new List<Transform>() };
        readonly float[] wheelOffset = { RestLength, RestLength };
        readonly List<Collider> colliders = new List<Collider>();
        int groundedWheels;
        Vector3 lastVelocity;
        float wheelSpin, flipTimer, stuckTimer, damageFlash;
        GameObject flame;
        Renderer bodyRenderer;
        Turret turret;

        static readonly Color BodyColor = new Color(0.78f, 0.27f, 0.14f);

        public static CarController Create(Transform parent, Vector3 pos, RunStats stats)
        {
            var go = new GameObject("Car");
            go.transform.SetParent(parent, false);
            go.transform.position = pos;
            go.AddComponent<Rigidbody>();
            var car = go.AddComponent<CarController>();
            car.stats = stats;
            car.Build();
            return car;
        }

        GameObject Part(PrimitiveType type, Vector3 pos, Vector3 scale, Color color, bool collider = false) =>
            Util.Prim(type, transform, pos, scale, color, collider);

        void Build()
        {
            rb = GetComponent<Rigidbody>();
            rb.mass = stats.mass;
            rb.interpolation = RigidbodyInterpolation.Interpolate;
            rb.collisionDetectionMode = CollisionDetectionMode.ContinuousDynamic;
            rb.constraints = RigidbodyConstraints.FreezePositionZ | RigidbodyConstraints.FreezeRotationX | RigidbodyConstraints.FreezeRotationY;
            rb.SetDamping(0.05f, 2f);

            var dark = new Color(0.12f, 0.12f, 0.12f);
            var metal = new Color(0.55f, 0.55f, 0.58f);

            // Body + cabin (with colliders, they join the rigidbody as a compound collider)
            bodyRenderer = Part(PrimitiveType.Cube, new Vector3(0f, 0.2f, 0f), new Vector3(3.8f, 0.7f, 1.8f), BodyColor, true).GetComponent<Renderer>();
            Part(PrimitiveType.Cube, new Vector3(-0.35f, 0.85f, 0f), new Vector3(1.8f, 0.65f, 1.6f), new Color(0.62f, 0.2f, 0.1f), true);
            // Windshield + side windows
            var glass = new Color(0.35f, 0.55f, 0.65f);
            Part(PrimitiveType.Cube, new Vector3(0.56f, 0.88f, 0f), new Vector3(0.06f, 0.5f, 1.5f), glass);
            Part(PrimitiveType.Cube, new Vector3(-0.35f, 0.9f, -0.81f), new Vector3(1.4f, 0.4f, 0.02f), glass);
            // Bumpers, plates, exhaust, roll bar
            Part(PrimitiveType.Cube, new Vector3(1.98f, 0.05f, 0f), new Vector3(0.25f, 0.5f, 1.95f), metal);
            Part(PrimitiveType.Cube, new Vector3(-1.98f, 0.1f, 0f), new Vector3(0.2f, 0.4f, 1.9f), metal);
            Part(PrimitiveType.Cube, new Vector3(1.2f, 0.58f, 0f), new Vector3(1.0f, 0.08f, 1.5f), metal); // hood plate
            Part(PrimitiveType.Cylinder, new Vector3(-1.95f, -0.15f, 0.6f), new Vector3(0.15f, 0.2f, 0.15f), dark).transform.localRotation = Quaternion.Euler(0f, 0f, 90f);
            for (int i = 0; i < 3; i++) // ram spikes
                Part(PrimitiveType.Cube, new Vector3(2.15f, 0.05f, -0.6f + i * 0.6f), new Vector3(0.35f, 0.08f, 0.08f), metal);

            // Wheels: 2 physics rays (rear/front), 4 visual wheels
            for (int i = 0; i < 2; i++)
            {
                foreach (float z in new[] { -0.85f, 0.85f })
                {
                    var w = new GameObject("Wheel");
                    w.transform.SetParent(transform, false);
                    w.transform.localPosition = new Vector3(WheelAnchors[i].x, WheelAnchors[i].y - RestLength, z);
                    var tire = Util.Prim(PrimitiveType.Cylinder, w.transform, Vector3.zero, new Vector3(0.9f, 0.15f, 0.9f), dark);
                    tire.transform.localRotation = Quaternion.Euler(90f, 0f, 0f);
                    float side = z > 0 ? 0.16f : -0.16f;
                    Util.Prim(PrimitiveType.Cube, w.transform, new Vector3(0f, 0f, side), new Vector3(0.55f, 0.12f, 0.04f), metal);
                    Util.Prim(PrimitiveType.Cube, w.transform, new Vector3(0f, 0f, side), new Vector3(0.12f, 0.55f, 0.04f), metal);
                    wheelVisuals[i].Add(w.transform);
                }
            }

            flame = Part(PrimitiveType.Cube, new Vector3(-2.25f, -0.1f, 0.6f), new Vector3(0.7f, 0.25f, 0.25f), new Color(1f, 0.6f, 0.1f));
            flame.GetComponent<Renderer>().sharedMaterial = Util.Unlit(new Color(1f, 0.65f, 0.15f));
            flame.SetActive(false);

            Util.SetLayerRecursive(gameObject, Layers.Car);
            GetComponentsInChildren(colliders);

            rb.centerOfMass = new Vector3(0.1f, -0.35f, 0f);

            Fuel = stats.fuelMax;
            Hp = stats.hpMax;
            Boost = stats.boostMax;
            if (stats.gunLevel > 0) EnsureTurret();
        }

        bool Controllable => !Dead && GameManager.Instance != null && GameManager.Instance.State == GameState.Running;

        void FixedUpdate()
        {
            if (rb == null) return;
            float dt = Time.fixedDeltaTime;
            bool control = Controllable;
            float drive = control ? GameInput.Drive : 0f;
            float tilt = control ? GameInput.Tilt : 0f;
            bool boostHeld = control && GameInput.Boost && Boost > 0f;

            // --- Raycast suspension ---
            groundedWheels = 0;
            Vector3 normalSum = Vector3.zero;
            float k = rb.mass * 30f, damper = rb.mass * 4f;
            float maxDist = RestLength + WheelRadius;
            for (int i = 0; i < 2; i++)
            {
                Vector3 origin = transform.TransformPoint(WheelAnchors[i]);
                if (Physics.Raycast(origin, -transform.up, out RaycastHit hit, maxDist, Layers.GroundMask, QueryTriggerInteraction.Ignore))
                {
                    groundedWheels++;
                    normalSum += hit.normal;
                    float compression = maxDist - hit.distance;
                    float springVel = Vector3.Dot(rb.GetPointVelocity(origin), transform.up);
                    float force = compression * k - springVel * damper;
                    if (force > 0f) rb.AddForceAtPosition(transform.up * force, origin);
                    wheelOffset[i] = hit.distance - WheelRadius;
                }
                else
                {
                    wheelOffset[i] = Mathf.MoveTowards(wheelOffset[i], RestLength, dt * 3f);
                }
            }

            Vector3 vel = rb.GetVel();
            float fwdSpeed = Vector3.Dot(vel, transform.right);

            if (groundedWheels > 0)
            {
                Vector3 n = normalSum.normalized;
                Vector3 fwd = Vector3.ProjectOnPlane(transform.right, n).normalized;
                float grip = groundedWheels / 2f;

                if (drive > 0f && Fuel > 0f)
                {
                    if (fwdSpeed < stats.maxSpeed) rb.AddForce(fwd * (stats.accel * drive * grip), ForceMode.Acceleration);
                    Fuel = Mathf.Max(0f, Fuel - stats.fuelUse * dt);
                }
                else if (drive < 0f)
                {
                    if (fwdSpeed > 0.5f)
                        rb.AddForce(-fwd * (14f * grip), ForceMode.Acceleration); // brake
                    else if (fwdSpeed > -7f && Fuel > 0f)
                    {
                        rb.AddForce(-fwd * (stats.accel * 0.5f * grip), ForceMode.Acceleration); // reverse
                        Fuel = Mathf.Max(0f, Fuel - stats.fuelUse * 0.5f * dt);
                    }
                }
                else
                {
                    // Rolling resistance; strong when almost stopped so the car parks on slopes.
                    float resist = Mathf.Abs(fwdSpeed) < 1.5f ? 6f : 0.6f;
                    float amount = Mathf.Min(resist, Mathf.Abs(fwdSpeed) / dt);
                    rb.AddForce(-fwd * (Mathf.Sign(fwdSpeed) * amount * grip), ForceMode.Acceleration);
                }

                rb.AddTorque(Vector3.forward * (tilt * stats.airControl * 0.3f), ForceMode.Acceleration);
            }
            else
            {
                // Air control: W/S rotate the car to land on the wheels.
                rb.AddTorque(Vector3.forward * (tilt * stats.airControl), ForceMode.Acceleration);
            }

            // --- Nitro boost (works in the air too) ---
            Boosting = boostHeld;
            if (boostHeld)
            {
                Boost = Mathf.Max(0f, Boost - dt);
                if (fwdSpeed < stats.maxSpeed * 1.5f) rb.AddForce(transform.right * stats.boostAccel, ForceMode.Acceleration);
            }
            else
            {
                Boost = Mathf.Min(stats.boostMax, Boost + stats.boostRegen * dt);
            }

            lastVelocity = rb.GetVel();
        }

        void Update()
        {
            if (rb == null) return;
            UpdateVisuals();
            if (Dead || GameManager.Instance == null || GameManager.Instance.State != GameState.Running) return;

            float speed = Speed;
            flipTimer = transform.up.y < 0.1f && speed < 2f ? flipTimer + Time.deltaTime : 0f;
            stuckTimer = Fuel <= 0f && speed < 0.6f ? stuckTimer + Time.deltaTime : 0f;

            if (Hp <= 0f) Die("Your car was destroyed");
            else if (flipTimer > 2.5f) Die("Flipped over");
            else if (stuckTimer > 1.5f) Die("Out of fuel");
            else if (transform.position.y < -60f) Die("Fell into the abyss");
        }

        void UpdateVisuals()
        {
            wheelSpin += ForwardSpeed / WheelRadius * Mathf.Rad2Deg * Time.deltaTime;
            for (int i = 0; i < 2; i++)
            {
                foreach (var w in wheelVisuals[i])
                {
                    var p = w.localPosition;
                    p.y = WheelAnchors[i].y - wheelOffset[i];
                    w.localPosition = p;
                    w.localRotation = Quaternion.Euler(0f, 0f, -wheelSpin);
                }
            }
            if (flame != null)
            {
                flame.SetActive(Boosting);
                if (Boosting) flame.transform.localScale = new Vector3(Random.Range(0.5f, 1.0f), 0.25f, 0.25f);
            }
            if (damageFlash > 0f)
            {
                damageFlash -= Time.deltaTime;
                bodyRenderer.sharedMaterial = Util.Mat(damageFlash > 0f ? Color.white : BodyColor);
            }
        }

        void OnCollisionEnter(Collision col)
        {
            if (Dead || rb == null) return;
            if (col.collider.GetComponentInParent<Zombie>() != null)
            {
                // Ramming: give back part of the momentum the zombie took (more with Spiked Ram).
                rb.SetVel(Vector3.Lerp(rb.GetVel(), lastVelocity, stats.ramPower));
                return; // the zombie handles its own death and the damage
            }
            float dv = col.impulse.magnitude / rb.mass;
            if (dv > 6f) Damage((dv - 6f) * 6f);
        }

        // ---------- Public API used by zombies, pickups and perks ----------
        public void Damage(float amount)
        {
            if (Dead || GameManager.Instance == null || GameManager.Instance.State != GameState.Running) return;
            Hp = Mathf.Max(0f, Hp - amount * stats.armor);
            damageFlash = 0.08f;
        }

        public void Heal(float amount) => Hp = Mathf.Min(stats.hpMax, Hp + amount);
        public void AddFuel(float amount) => Fuel = Mathf.Min(stats.fuelMax, Fuel + amount);
        public void RefillBoost() => Boost = stats.boostMax;
        public void ApplyMass() { if (rb != null) rb.mass = stats.mass; }

        public void EnsureTurret()
        {
            if (turret != null) return;
            turret = gameObject.AddComponent<Turret>();
            turret.Init(this);
        }

        public void IgnoreCollisionsWith(Collider other)
        {
            foreach (var c in colliders)
                if (c != null && other != null) Physics.IgnoreCollision(c, other);
        }

        void Die(string reason)
        {
            Dead = true;
            Boosting = false;
            GameManager.Instance.EndRun(false, reason);
        }
    }
}
