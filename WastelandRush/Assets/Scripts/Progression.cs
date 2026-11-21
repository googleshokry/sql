using System.Collections.Generic;
using UnityEngine;

namespace WastelandRush
{
    public enum UpgradeType { Engine, FuelTank, Armor, Nitro, Suspension, Turret }

    /// <summary>Permanent progress, stored in PlayerPrefs as JSON.</summary>
    [System.Serializable]
    public class SaveData
    {
        public int coins;
        public int stage = 1;
        public float bestDistance;
        public int totalRuns;
        public int[] levels = new int[6];
    }

    /// <summary>Stats for a single run: base values from upgrades, then modified by perks.</summary>
    public class RunStats
    {
        public float accel, maxSpeed, fuelMax, fuelUse, hpMax, armor;
        public float boostMax, boostAccel, boostRegen, airControl, ramPower, mass;
        public float coinMult = 1f, fuelPickup = 30f;
        public int gunLevel;
        public readonly List<string> perks = new List<string>();
    }

    public static class Upgrades
    {
        public const int MaxLevel = 5;
        public static readonly string[] Names = { "Engine", "Fuel Tank", "Armor", "Nitro", "Suspension", "Roof Turret" };
        public static readonly string[] Descs =
        {
            "Acceleration & top speed",
            "More fuel every run",
            "More hull HP, less damage",
            "Longer, stronger boost",
            "Better air control",
            "Auto-shoots zombies ahead",
        };
        static readonly int[] BaseCost = { 60, 50, 55, 70, 45, 150 };

        public static int Cost(UpgradeType t, int level) =>
            Mathf.RoundToInt(BaseCost[(int)t] * Mathf.Pow(1.75f, level));

        public static RunStats BuildStats(SaveData s)
        {
            int L(UpgradeType t) => s.levels[(int)t];
            return new RunStats
            {
                accel = 9f + 1.8f * L(UpgradeType.Engine),
                maxSpeed = 20f + 3.5f * L(UpgradeType.Engine),
                fuelMax = 70f + 25f * L(UpgradeType.FuelTank),
                fuelUse = 4f,
                hpMax = 100f + 40f * L(UpgradeType.Armor),
                armor = 1f - 0.1f * L(UpgradeType.Armor),
                boostMax = 2f + 0.8f * L(UpgradeType.Nitro),
                boostAccel = 12f + 2f * L(UpgradeType.Nitro),
                boostRegen = 0.1f,
                airControl = 5f + 1.3f * L(UpgradeType.Suspension),
                ramPower = 0.25f,
                mass = 900f,
                gunLevel = L(UpgradeType.Turret),
            };
        }
    }

    /// <summary>Roguelike perk offered by supply drops during a run. Lost when the run ends.</summary>
    public class Perk
    {
        public readonly string name, desc;
        public readonly System.Action<RunStats, CarController> apply;

        public Perk(string name, string desc, System.Action<RunStats, CarController> apply)
        {
            this.name = name; this.desc = desc; this.apply = apply;
        }
    }

    public static class Perks
    {
        public static readonly List<Perk> All = new List<Perk>
        {
            new Perk("Turbocharger", "+20% acceleration\n+10% top speed", (s, c) => { s.accel *= 1.2f; s.maxSpeed *= 1.1f; }),
            new Perk("Spiked Ram", "Zombies barely slow\nyou down", (s, c) => s.ramPower = Mathf.Min(0.95f, s.ramPower + 0.35f)),
            new Perk("Reserve Tank", "+40 max fuel\nand refuel 40", (s, c) => { s.fuelMax += 40f; c.AddFuel(40f); }),
            new Perk("Scrap Armor", "+60 max HP\nand repair 60", (s, c) => { s.hpMax += 60f; c.Heal(60f); }),
            new Perk("Nitro Cells", "+60% boost capacity\nand refill boost", (s, c) => { s.boostMax *= 1.6f; c.RefillBoost(); }),
            new Perk("Blood Money", "Kills earn x2 coins", (s, c) => s.coinMult *= 2f),
            new Perk("Roof Turret", "+1 turret level", (s, c) => { s.gunLevel++; c.EnsureTurret(); }),
            new Perk("Gyroscope", "Double air control", (s, c) => s.airControl *= 2f),
            new Perk("Eco Injector", "-35% fuel consumption", (s, c) => s.fuelUse *= 0.65f),
            new Perk("Scavenger", "Fuel cans give +75%", (s, c) => s.fuelPickup *= 1.75f),
            new Perk("Heavy Chassis", "+40% mass:\nplow through hordes", (s, c) => { s.mass *= 1.4f; c.ApplyMass(); }),
            new Perk("Afterburner", "Boost recharges\n3x faster", (s, c) => s.boostRegen *= 3f),
        };

        public static List<Perk> RandomChoices(int n)
        {
            var pool = new List<Perk>(All);
            var result = new List<Perk>();
            for (int i = 0; i < n && pool.Count > 0; i++)
            {
                int j = Random.Range(0, pool.Count);
                result.Add(pool[j]);
                pool.RemoveAt(j);
            }
            return result;
        }
    }
}
