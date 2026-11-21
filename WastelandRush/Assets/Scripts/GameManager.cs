using System.Collections.Generic;
using UnityEngine;
using UnityEngine.Rendering;

namespace WastelandRush
{
    public enum GameState { Garage, Running, PerkChoice, Results }

    /// <summary>
    /// Creates itself automatically when you press Play in ANY scene (see Bootstrap),
    /// so no manual scene setup is required.
    /// </summary>
    public static class Bootstrap
    {
        [RuntimeInitializeOnLoadMethod(RuntimeInitializeLoadType.AfterSceneLoad)]
        static void Init()
        {
            Zombie.Alive.Clear();
            if (GameManager.Instance == null)
                new GameObject("GameManager").AddComponent<GameManager>();
        }
    }

    public class GameManager : MonoBehaviour
    {
        public static GameManager Instance { get; private set; }

        public GameState State { get; private set; } = GameState.Garage;
        public SaveData Save { get; private set; }
        public RunStats Stats { get; private set; }
        public CarController Car { get; private set; }
        public LevelGenerator Level { get; private set; }

        // Current run
        public int RunKills { get; private set; }
        public int RunKillCoins { get; private set; }
        public float RunDistance { get; private set; }
        public List<Perk> PerkOptions { get; private set; } = new List<Perk>();

        // Last result
        public bool LastWin { get; private set; }
        public string LastReason { get; private set; }
        public int LastDistanceCoins { get; private set; }
        public int LastWinBonus { get; private set; }
        public int LastEarned { get; private set; }
        public int LastStage { get; private set; }
        public bool LastNewBest { get; private set; }

        public class ToastMsg { public string text; public float time; }
        public readonly List<ToastMsg> Toasts = new List<ToastMsg>();

        const string SaveKey = "WastelandRush.Save.v1";
        GameObject world;
        CameraFollow cam;

        void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(gameObject); return; }
            Instance = this;
            Load();
            SetupScene();
            gameObject.AddComponent<GameUI>();
            BuildWorld();
        }

        void OnDestroy()
        {
            if (Instance == this) Instance = null;
        }

        // ---------------- Scene ----------------
        void SetupScene()
        {
            Time.fixedDeltaTime = 1f / 60f;
            var sky = new Color(0.9f, 0.58f, 0.38f);

            Camera c = Camera.main;
            if (c == null)
            {
                var go = new GameObject("Main Camera") { tag = "MainCamera" };
                c = go.AddComponent<Camera>();
                go.AddComponent<AudioListener>();
            }
            c.clearFlags = CameraClearFlags.SolidColor;
            c.backgroundColor = sky;
            c.fieldOfView = 50f;
            c.nearClipPlane = 0.3f;
            c.farClipPlane = 400f;
            cam = c.GetComponent<CameraFollow>();
            if (cam == null) cam = c.gameObject.AddComponent<CameraFollow>();

            // Disable scene directional lights; use our own sun.
#if UNITY_2022_2_OR_NEWER
            var lights = FindObjectsByType<Light>(FindObjectsSortMode.None);
#else
            var lights = FindObjectsOfType<Light>();
#endif
            foreach (var l in lights) if (l.type == LightType.Directional) l.enabled = false;

            var sun = new GameObject("Sun").AddComponent<Light>();
            sun.type = LightType.Directional;
            sun.color = new Color(1f, 0.88f, 0.72f);
            sun.intensity = 1.15f;
            sun.shadows = LightShadows.Soft;
            sun.transform.rotation = Quaternion.Euler(42f, -35f, 0f);
            sun.transform.SetParent(transform);

            RenderSettings.ambientMode = AmbientMode.Flat;
            RenderSettings.ambientLight = new Color(0.55f, 0.45f, 0.42f);
            RenderSettings.fog = true;
            RenderSettings.fogMode = FogMode.Linear;
            RenderSettings.fogColor = sky;
            RenderSettings.fogStartDistance = 50f;
            RenderSettings.fogEndDistance = 200f;
        }

        void BuildWorld()
        {
            if (world != null) Destroy(world);
            Zombie.Alive.Clear();
            Time.timeScale = 1f;

            world = new GameObject("World");
            Level = world.AddComponent<LevelGenerator>();
            Level.Init(Save.stage, Random.Range(0, int.MaxValue));

            Stats = Upgrades.BuildStats(Save);
            float x = LevelGenerator.StartX;
            Car = CarController.Create(world.transform, new Vector3(x, Level.Height(x) + 1.6f, 0f), Stats);
            Level.SetCar(Car);
            cam.SetTarget(Car.transform);
        }

        // ---------------- Flow ----------------
        public void StartRun()
        {
            BuildWorld();
            RunKills = 0;
            RunKillCoins = 0;
            RunDistance = 0f;
            Toasts.Clear();
            Save.totalRuns++;
            State = GameState.Running;
            Toast("Reach the evacuation point: " + Mathf.RoundToInt(Level.GoalDistance) + " m");
        }

        public void GoToGarage()
        {
            BuildWorld();
            State = GameState.Garage;
        }

        void Update()
        {
            if (State == GameState.Running && Car != null)
            {
                RunDistance = Mathf.Max(RunDistance, Car.transform.position.x - LevelGenerator.StartX);
                if (GameInput.AbortPressed) EndRun(false, "Run abandoned");
            }
            for (int i = Toasts.Count - 1; i >= 0; i--)
            {
                Toasts[i].time -= Time.unscaledDeltaTime;
                if (Toasts[i].time <= 0f) Toasts.RemoveAt(i);
            }
        }

        public void Toast(string text)
        {
            Toasts.Add(new ToastMsg { text = text, time = 2.2f });
            if (Toasts.Count > 4) Toasts.RemoveAt(0);
        }

        public void RegisterKill(int coins)
        {
            if (State != GameState.Running) return;
            RunKills++;
            RunKillCoins += Mathf.RoundToInt(coins * Stats.coinMult);
        }

        public void OfferPerks()
        {
            if (State != GameState.Running) return;
            PerkOptions = Perks.RandomChoices(3);
            State = GameState.PerkChoice;
            Time.timeScale = 0f;
        }

        public void ChoosePerk(int index)
        {
            if (State != GameState.PerkChoice || index < 0 || index >= PerkOptions.Count) return;
            var p = PerkOptions[index];
            p.apply(Stats, Car);
            Stats.perks.Add(p.name);
            State = GameState.Running;
            Time.timeScale = 1f;
            Toast("Perk: " + p.name);
        }

        public void EndRun(bool win, string reason)
        {
            if (State != GameState.Running) return;
            Time.timeScale = 1f;

            LastWin = win;
            LastReason = reason;
            LastStage = Save.stage;
            LastDistanceCoins = Mathf.RoundToInt(RunDistance * 0.4f);
            LastWinBonus = win ? 150 + 100 * Save.stage : 0;
            LastEarned = LastDistanceCoins + RunKillCoins + LastWinBonus;
            LastNewBest = RunDistance > Save.bestDistance;

            Save.coins += LastEarned;
            if (LastNewBest) Save.bestDistance = RunDistance;
            if (win) Save.stage++;
            Persist();

            State = GameState.Results;
        }

        // ---------------- Shop ----------------
        public int UpgradeLevel(UpgradeType t) => Save.levels[(int)t];

        public bool CanBuy(UpgradeType t)
        {
            int lvl = UpgradeLevel(t);
            return lvl < Upgrades.MaxLevel && Save.coins >= Upgrades.Cost(t, lvl);
        }

        public void Buy(UpgradeType t)
        {
            if (State != GameState.Garage || !CanBuy(t)) return;
            Save.coins -= Upgrades.Cost(t, UpgradeLevel(t));
            Save.levels[(int)t]++;
            Persist();
            BuildWorld(); // refresh the car preview
        }

        public void ResetProgress()
        {
            Save = new SaveData();
            Persist();
            GoToGarage();
        }

        // ---------------- Save ----------------
        void Load()
        {
            Save = null;
            if (PlayerPrefs.HasKey(SaveKey))
            {
                try { Save = JsonUtility.FromJson<SaveData>(PlayerPrefs.GetString(SaveKey)); }
                catch { Save = null; }
            }
            if (Save == null) Save = new SaveData();
            int count = System.Enum.GetValues(typeof(UpgradeType)).Length;
            if (Save.levels == null || Save.levels.Length != count)
            {
                var levels = new int[count];
                if (Save.levels != null)
                    for (int i = 0; i < Mathf.Min(count, Save.levels.Length); i++) levels[i] = Save.levels[i];
                Save.levels = levels;
            }
            if (Save.stage < 1) Save.stage = 1;
        }

        void Persist()
        {
            PlayerPrefs.SetString(SaveKey, JsonUtility.ToJson(Save));
            PlayerPrefs.Save();
        }
    }
}
