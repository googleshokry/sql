using System.Collections.Generic;
using UnityEngine;

namespace WastelandRush
{
    /// <summary>
    /// Streams procedural terrain chunks (hills + jump ramps) along +X and fills them with
    /// zombies, obstacles, fuel cans, supply drops and the evacuation goal.
    /// </summary>
    public class LevelGenerator : MonoBehaviour
    {
        public const float ChunkLength = 50f;
        public const float Step = 1f;
        public const float HalfWidth = 5f;
        public const float PerkInterval = 300f;
        public const float StartX = 8f;

        public float GoalDistance { get; private set; }
        public static float GoalFor(int stage) => 800f + stage * 400f;

        class Chunk { public GameObject root; public Mesh mesh; }

        struct Ramp { public float x, length, height; }

        readonly Dictionary<int, Chunk> chunks = new Dictionary<int, Chunk>();
        readonly List<Ramp> ramps = new List<Ramp>();
        readonly List<int> toRemove = new List<int>();
        System.Random rng;
        CarController car;
        int stage;
        float seedA, seedB, nextRampX = 110f;

        static readonly Color Sand = new Color(0.78f, 0.62f, 0.4f);
        static readonly Color Dirt = new Color(0.45f, 0.3f, 0.2f);

        float R(float min, float max) => min + (float)rng.NextDouble() * (max - min);
        bool Chance(float p) => rng.NextDouble() < p;

        public void Init(int stage, int seed)
        {
            this.stage = stage;
            rng = new System.Random(seed);
            seedA = R(0f, 1000f);
            seedB = R(0f, 1000f);
            GoalDistance = GoalFor(stage);
            for (int i = 0; i < 4; i++) GenerateChunk(i);
        }

        public void SetCar(CarController c) => car = c;

        // ---------------- Terrain shape ----------------
        float BaseHeight(float x)
        {
            float h = (Mathf.PerlinNoise(seedA + x * 0.008f, 0.5f) - 0.5f) * 30f
                    + (Mathf.PerlinNoise(seedB + x * 0.04f, 0.5f) - 0.5f) * 4f;
            float start = Mathf.Clamp01((x - 30f) / 80f); // flat start area
            float baseline = (Mathf.PerlinNoise(seedA, 0.5f) - 0.5f) * 30f;
            return Mathf.Lerp(0f, h - baseline, start);
        }

        public float Height(float x)
        {
            float h = BaseHeight(x);
            foreach (var r in ramps)
                if (x >= r.x && x <= r.x + r.length) h += (x - r.x) / r.length * r.height;

            float g0 = GoalDistance - 40f;
            if (x > g0) h = Mathf.Lerp(h, BaseHeight(GoalDistance), Mathf.Clamp01((x - g0) / 25f));
            return h;
        }

        void EnsureRamps(float upToX)
        {
            while (nextRampX < upToX + 20f)
            {
                float len = Mathf.Round(R(9f, 14f));
                float x = Mathf.Round(nextRampX);
                if (x + len < GoalDistance - 60f)
                    ramps.Add(new Ramp { x = x, length = len, height = R(2.2f, 3.5f + stage * 0.3f) });
                nextRampX += R(90f, 190f);
            }
        }

        // ---------------- Streaming ----------------
        void Update()
        {
            float cx = car != null ? car.transform.position.x : 0f;
            int cur = Mathf.FloorToInt(cx / ChunkLength);
            for (int i = cur - 1; i <= cur + 3; i++)
                if (i >= 0 && !chunks.ContainsKey(i)) GenerateChunk(i);

            toRemove.Clear();
            foreach (var kv in chunks)
                if (kv.Key < cur - 2) toRemove.Add(kv.Key);
            foreach (int key in toRemove)
            {
                Destroy(chunks[key].root);
                Destroy(chunks[key].mesh);
                chunks.Remove(key);
            }
        }

        void OnDestroy()
        {
            foreach (var c in chunks.Values) if (c.mesh != null) Destroy(c.mesh);
        }

        void GenerateChunk(int index)
        {
            float x0 = index * ChunkLength;
            EnsureRamps(x0 + ChunkLength);

            var root = new GameObject("Chunk_" + index);
            root.transform.SetParent(transform, false);
            var mesh = BuildGround(root.transform, x0);
            chunks[index] = new Chunk { root = root, mesh = mesh };

            BuildScenery(root.transform, x0);

            if (index == 0)
            {
                // Invisible wall behind the start
                var wall = new GameObject("BackWall");
                wall.transform.SetParent(root.transform, false);
                wall.transform.position = new Vector3(-3f, 0f, 0f);
                wall.AddComponent<BoxCollider>().size = new Vector3(1f, 100f, 20f);
                return; // safe start zone
            }

            SpawnContent(root.transform, x0);
        }

        Mesh BuildGround(Transform parent, float x0)
        {
            int n = Mathf.RoundToInt(ChunkLength / Step) + 1;
            var verts = new List<Vector3>(n * 4);
            var top = new List<int>();
            var side = new List<int>();

            for (int i = 0; i < n; i++)
            {
                float x = x0 + i * Step;
                float y = Height(x);
                verts.Add(new Vector3(x, y, -HalfWidth));
                verts.Add(new Vector3(x, y, HalfWidth));
            }
            for (int i = 0; i < n - 1; i++)
            {
                int a = i * 2;
                top.Add(a); top.Add(a + 1); top.Add(a + 2);
                top.Add(a + 1); top.Add(a + 3); top.Add(a + 2);
            }

            int b = verts.Count;
            for (int i = 0; i < n; i++)
            {
                float x = x0 + i * Step;
                verts.Add(new Vector3(x, verts[i * 2].y, -HalfWidth));
                verts.Add(new Vector3(x, -45f, -HalfWidth));
            }
            for (int i = 0; i < n - 1; i++)
            {
                int f0 = b + i * 2;
                side.Add(f0); side.Add(f0 + 2); side.Add(f0 + 1);
                side.Add(f0 + 2); side.Add(f0 + 3); side.Add(f0 + 1);
            }

            var mesh = new Mesh { name = "Ground" };
            mesh.SetVertices(verts);
            mesh.subMeshCount = 2;
            mesh.SetTriangles(top, 0);
            mesh.SetTriangles(side, 1);
            mesh.RecalculateNormals();
            mesh.RecalculateBounds();

            var ground = new GameObject("Ground");
            ground.transform.SetParent(parent, false);
            ground.layer = Layers.Ground;
            ground.AddComponent<MeshFilter>().sharedMesh = mesh;
            ground.AddComponent<MeshRenderer>().sharedMaterials = new[] { Util.Mat(Sand), Util.Mat(Dirt) };
            ground.AddComponent<MeshCollider>().sharedMesh = mesh;
            return mesh;
        }

        void BuildScenery(Transform parent, float x0)
        {
            var trunk = new Color(0.3f, 0.22f, 0.15f);
            var rock = new Color(0.5f, 0.45f, 0.42f);
            // dead trees and rocks behind the road
            for (int i = 0; i < 4; i++)
            {
                float x = x0 + R(0f, ChunkLength);
                float z = R(2.5f, 4.5f);
                var p = new Vector3(x, Height(x) - 0.2f, z);
                if (Chance(0.5f))
                {
                    float hgt = R(2.5f, 5f);
                    Util.Prim(PrimitiveType.Cylinder, parent, p + Vector3.up * hgt * 0.5f, new Vector3(0.25f, hgt * 0.5f, 0.25f), trunk);
                    var br = Util.Prim(PrimitiveType.Cube, parent, p + Vector3.up * hgt * 0.75f + Vector3.right * 0.4f, new Vector3(1.2f, 0.12f, 0.12f), trunk);
                    br.transform.localRotation = Quaternion.Euler(0f, 0f, R(20f, 50f));
                }
                else
                {
                    var s = R(0.6f, 1.8f);
                    Util.Prim(PrimitiveType.Sphere, parent, p, new Vector3(s * 1.4f, s, s), rock);
                }
            }
            // distant ruined buildings (fog makes them read as background)
            for (int i = 0; i < 2; i++)
            {
                float x = x0 + R(0f, ChunkLength);
                float h = R(8f, 30f);
                var c = new Color(0.4f, 0.35f, 0.38f);
                Util.Prim(PrimitiveType.Cube, parent, new Vector3(x, Height(x) + h * 0.5f - 6f, R(35f, 60f)), new Vector3(R(6f, 14f), h, 8f), c);
            }
            // occasional road sign / power pole
            if (Chance(0.4f))
            {
                float x = x0 + R(0f, ChunkLength);
                var p = new Vector3(x, Height(x), 3.5f);
                Util.Prim(PrimitiveType.Cylinder, parent, p + Vector3.up * 3f, new Vector3(0.15f, 3f, 0.15f), trunk);
                Util.Prim(PrimitiveType.Cube, parent, p + Vector3.up * 5.5f, new Vector3(0.1f, 0.1f, 1.8f), trunk);
            }
        }

        void SpawnContent(Transform parent, float x0)
        {
            float x1 = x0 + ChunkLength;
            float density = 1f + stage * 0.35f + x0 / 600f;
            float goalCut = GoalDistance - 30f;

            // Zombie groups & hordes
            int groups = Mathf.RoundToInt(2f + density * 1.5f);
            for (int g = 0; g < groups; g++)
            {
                float gx = x0 + R(0f, ChunkLength);
                bool horde = Chance(0.15f + stage * 0.03f);
                int count = horde ? rng.Next(6, 12 + stage * 2) : rng.Next(1, 4);
                for (int j = 0; j < count; j++)
                {
                    float x = gx + j * 0.85f + R(-0.2f, 0.2f);
                    if (x > goalCut) break;
                    var kind = Zombie.Kind.Walker;
                    double roll = rng.NextDouble();
                    if (x0 > 150f && roll < 0.06f + stage * 0.02f) kind = Zombie.Kind.Brute;
                    else if (roll < 0.28f) kind = Zombie.Kind.Runner;
                    Zombie.Spawn(parent, new Vector3(x, Height(x) + 0.05f, 0f), kind);
                }
            }

            // Crate stacks
            if (Chance(0.35f))
            {
                float x = x0 + R(5f, ChunkLength - 5f);
                if (x < goalCut)
                {
                    int stack = rng.Next(1, 4);
                    for (int i = 0; i < stack; i++)
                        SpawnBox(parent, new Vector3(x + (i % 2) * 0.3f, Height(x) + 0.55f + i * 1.05f, 0f), Vector3.one, new Color(0.55f, 0.38f, 0.2f), 30f);
                }
            }

            // Wrecked car
            if (x0 > 100f && Chance(0.18f + stage * 0.02f))
            {
                float x = x0 + R(5f, ChunkLength - 5f);
                if (x < goalCut)
                {
                    var wreck = SpawnBox(parent, new Vector3(x, Height(x) + 0.75f, 0f), new Vector3(3.6f, 1.2f, 1.8f), new Color(0.35f, 0.33f, 0.3f), 350f);
                    Util.Prim(PrimitiveType.Cube, wreck.transform, new Vector3(-0.05f, 0.85f, 0f), new Vector3(0.5f, 0.6f, 0.9f), new Color(0.3f, 0.2f, 0.15f));
                    wreck.GetComponent<Rigidbody>().constraints = RigidbodyConstraints.FreezePositionZ | RigidbodyConstraints.FreezeRotationX | RigidbodyConstraints.FreezeRotationY;
                }
            }

            // Fuel cans
            if (Chance(0.6f))
            {
                float x = x0 + R(3f, ChunkLength - 3f);
                if (x < goalCut) Pickup.Create(parent, new Vector3(x, Height(x), 0f), Pickup.Type.Fuel);
            }

            // Supply drops (roguelike perk choice) every PerkInterval meters
            for (float d = PerkInterval; d < GoalDistance - 50f; d += PerkInterval)
            {
                float x = d + StartX;
                if (x >= x0 && x < x1) Pickup.Create(parent, new Vector3(x, Height(x), 0f), Pickup.Type.Supply);
            }

            // Evacuation point
            float gxGoal = GoalDistance + StartX;
            if (gxGoal >= x0 && gxGoal < x1) Pickup.Create(parent, new Vector3(gxGoal, Height(gxGoal), 0f), Pickup.Type.Goal);
        }

        GameObject SpawnBox(Transform parent, Vector3 pos, Vector3 size, Color color, float mass)
        {
            var go = Util.Prim(PrimitiveType.Cube, parent, Vector3.zero, size, color, true);
            go.transform.position = pos;
            Util.SetLayerRecursive(go, Layers.Props);
            var rb = go.AddComponent<Rigidbody>();
            rb.mass = mass;
            return go;
        }
    }
}
