using UnityEngine;

namespace WastelandRush
{
    /// <summary>Roof-mounted gun that auto-fires at the nearest zombie ahead of the car.</summary>
    public class Turret : MonoBehaviour
    {
        CarController car;
        Transform barrel;
        LineRenderer tracer;
        float cooldown, tracerTimer;

        public void Init(CarController c)
        {
            car = c;
            var mount = Util.Prim(PrimitiveType.Cube, transform, new Vector3(-0.35f, 1.3f, 0f), new Vector3(0.45f, 0.3f, 0.45f), new Color(0.3f, 0.3f, 0.32f));
            barrel = Util.Prim(PrimitiveType.Cube, transform, new Vector3(-0.1f, 1.38f, 0f), new Vector3(0.9f, 0.12f, 0.12f), new Color(0.1f, 0.1f, 0.1f)).transform;
            mount.layer = Layers.Car;
            barrel.gameObject.layer = Layers.Car;

            var lgo = new GameObject("Tracer");
            lgo.transform.SetParent(transform, false);
            tracer = lgo.AddComponent<LineRenderer>();
            tracer.positionCount = 2;
            tracer.useWorldSpace = true;
            tracer.startWidth = tracer.endWidth = 0.08f;
            tracer.sharedMaterial = Util.Unlit(new Color(1f, 0.9f, 0.3f));
            tracer.enabled = false;
        }

        void Update()
        {
            if (car == null) return;
            if (tracerTimer > 0f)
            {
                tracerTimer -= Time.deltaTime;
                if (tracerTimer <= 0f) tracer.enabled = false;
            }
            var gm = GameManager.Instance;
            if (car.Dead || gm == null || gm.State != GameState.Running) return;

            int level = car.stats.gunLevel;
            if (level <= 0) return;
            cooldown -= Time.deltaTime;

            float range = 18f + level * 3f;
            Vector3 p = car.transform.position;
            Zombie best = null;
            float bestDx = float.MaxValue;
            foreach (var z in Zombie.Alive)
            {
                if (z == null || z.IsDead) continue;
                float dx = z.transform.position.x - p.x;
                if (dx < 2.5f || dx > range) continue;
                if (dx < bestDx) { bestDx = dx; best = z; }
            }
            if (best == null) return;

            Vector3 target = best.transform.position + Vector3.up * 1.2f * best.transform.localScale.y;
            Vector3 dir = (target - barrel.position).normalized;
            barrel.rotation = Quaternion.FromToRotation(Vector3.right, dir);
            if (cooldown > 0f) return;

            cooldown = 1.1f / level;
            Vector3 muzzle = barrel.position + dir * 0.5f;
            tracer.SetPosition(0, muzzle);
            tracer.SetPosition(1, target);
            tracer.enabled = true;
            tracerTimer = 0.05f;
            best.Kill(dir * 14f, car);
        }
    }
}
