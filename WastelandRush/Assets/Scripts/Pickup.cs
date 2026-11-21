using UnityEngine;

namespace WastelandRush
{
    public class Pickup : MonoBehaviour
    {
        public enum Type { Fuel, Supply, Goal }

        public Type type;
        Transform visual;
        Vector3 visualBase;
        bool used;

        public static Pickup Create(Transform parent, Vector3 pos, Type type)
        {
            var go = new GameObject("Pickup_" + type);
            go.transform.SetParent(parent, false);
            go.transform.position = pos;
            var p = go.AddComponent<Pickup>();
            p.type = type;
            p.Build();
            return p;
        }

        void Build()
        {
            var trigger = gameObject.AddComponent<BoxCollider>();
            trigger.isTrigger = true;
            visual = new GameObject("Visual").transform;
            visual.SetParent(transform, false);

            switch (type)
            {
                case Type.Fuel:
                    trigger.size = new Vector3(1.5f, 5f, 4f);
                    trigger.center = new Vector3(0f, 1.5f, 0f);
                    visual.localPosition = new Vector3(0f, 1.2f, 0f);
                    Util.Prim(PrimitiveType.Cube, visual, Vector3.zero, new Vector3(0.6f, 0.8f, 0.35f), new Color(0.85f, 0.1f, 0.1f));
                    Util.Prim(PrimitiveType.Cube, visual, new Vector3(0.15f, 0.5f, 0f), new Vector3(0.15f, 0.2f, 0.15f), new Color(0.9f, 0.8f, 0.2f));
                    Util.Prim(PrimitiveType.Cube, visual, new Vector3(-0.1f, 0.45f, 0f), new Vector3(0.3f, 0.08f, 0.2f), new Color(0.2f, 0.2f, 0.2f));
                    break;

                case Type.Supply:
                    trigger.size = new Vector3(3f, 60f, 8f);
                    visual.localPosition = new Vector3(0f, 4f, 0f);
                    Util.Prim(PrimitiveType.Cube, visual, Vector3.zero, Vector3.one * 1.2f, new Color(0.95f, 0.75f, 0.1f));
                    Util.Prim(PrimitiveType.Cube, visual, Vector3.zero, new Vector3(1.25f, 0.2f, 1.25f), new Color(0.3f, 0.25f, 0.1f));
                    Util.Prim(PrimitiveType.Sphere, visual, new Vector3(0f, 2.2f, 0f), new Vector3(3f, 1.2f, 3f), new Color(0.95f, 0.95f, 0.9f));
                    var beam = Util.Prim(PrimitiveType.Cylinder, transform, new Vector3(0f, 10f, 0f), new Vector3(0.25f, 10f, 0.25f), Color.yellow);
                    beam.GetComponent<Renderer>().sharedMaterial = Util.Unlit(new Color(1f, 0.9f, 0.2f, 0.5f));
                    break;

                case Type.Goal:
                    trigger.size = new Vector3(4f, 100f, 12f);
                    var green = new Color(0.2f, 0.75f, 0.3f);
                    Util.Prim(PrimitiveType.Cube, transform, new Vector3(0f, 4f, -4.5f), new Vector3(0.6f, 8f, 0.6f), green);
                    Util.Prim(PrimitiveType.Cube, transform, new Vector3(0f, 4f, 4.5f), new Vector3(0.6f, 8f, 0.6f), green);
                    Util.Prim(PrimitiveType.Cube, transform, new Vector3(0f, 8f, 0f), new Vector3(0.6f, 1.4f, 9.6f), new Color(0.95f, 0.95f, 0.95f));
                    Util.Prim(PrimitiveType.Cube, transform, new Vector3(0f, 8f, -0.05f), new Vector3(0.62f, 0.5f, 9.0f), green);
                    // helipad
                    Util.Prim(PrimitiveType.Cylinder, transform, new Vector3(18f, 0.02f, 2f), new Vector3(10f, 0.05f, 6f), new Color(0.3f, 0.3f, 0.32f));
                    Util.Prim(PrimitiveType.Cube, transform, new Vector3(18f, 0.08f, 2f), new Vector3(4f, 0.05f, 0.6f), Color.white);
                    break;
            }
            Util.SetLayerRecursive(gameObject, Layers.Props);
            visualBase = visual.localPosition;
        }

        void Update()
        {
            if (visual == null || type == Type.Goal) return;
            visual.Rotate(0f, 90f * Time.deltaTime, 0f, Space.World);
            visual.localPosition = visualBase + Vector3.up * Mathf.Sin(Time.time * 2f) * 0.25f;
        }

        void OnTriggerEnter(Collider other)
        {
            if (used) return;
            var car = other.GetComponentInParent<CarController>();
            var gm = GameManager.Instance;
            if (car == null || car.Dead || gm == null || gm.State != GameState.Running) return;
            used = true;

            switch (type)
            {
                case Type.Fuel:
                    car.AddFuel(car.stats.fuelPickup);
                    gm.Toast("+FUEL");
                    Destroy(gameObject);
                    break;
                case Type.Supply:
                    gm.OfferPerks();
                    Destroy(gameObject);
                    break;
                case Type.Goal:
                    gm.EndRun(true, "You reached the evacuation point!");
                    break;
            }
        }
    }
}
