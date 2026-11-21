using UnityEngine;

namespace WastelandRush
{
    /// <summary>All UI is IMGUI (OnGUI) so it needs no Canvas / prefab setup.
    /// Laid out on a virtual 720px-high screen and scaled to the real resolution.</summary>
    public class GameUI : MonoBehaviour
    {
        const float RefH = 720f;
        GameManager gm;
        GUIStyle title, header, label, small, button, bigButton, center, cardTitle, cardText;
        Texture2D tex;

        static readonly Color Panel = new Color(0.08f, 0.06f, 0.06f, 0.82f);
        static readonly Color Gold = new Color(1f, 0.82f, 0.25f);
        static readonly Color Accent = new Color(0.95f, 0.45f, 0.2f);

        float Scale => Screen.height / RefH;
        float W => Screen.width / Scale;
        float H => RefH;

        void Awake()
        {
            gm = GetComponent<GameManager>();
            tex = Texture2D.whiteTexture;
        }

        void Styles()
        {
            if (title != null) return;
            label = new GUIStyle(GUI.skin.label) { fontSize = 20, richText = true };
            label.normal.textColor = Color.white;
            small = new GUIStyle(label) { fontSize = 15 };
            small.normal.textColor = new Color(0.85f, 0.85f, 0.85f);
            header = new GUIStyle(label) { fontSize = 26, fontStyle = FontStyle.Bold };
            title = new GUIStyle(label) { fontSize = 54, fontStyle = FontStyle.Bold };
            title.normal.textColor = Accent;
            center = new GUIStyle(label) { alignment = TextAnchor.MiddleCenter };
            button = new GUIStyle(GUI.skin.button) { fontSize = 18, fontStyle = FontStyle.Bold };
            bigButton = new GUIStyle(button) { fontSize = 30 };
            cardTitle = new GUIStyle(header) { alignment = TextAnchor.MiddleCenter, wordWrap = true };
            cardTitle.normal.textColor = Gold;
            cardText = new GUIStyle(label) { alignment = TextAnchor.MiddleCenter, wordWrap = true };
        }

        void OnGUI()
        {
            if (gm == null) return;
            Styles();
            GUI.matrix = Matrix4x4.Scale(new Vector3(Scale, Scale, 1f));

            switch (gm.State)
            {
                case GameState.Garage: DrawGarage(); break;
                case GameState.Running: DrawHud(); break;
                case GameState.PerkChoice: DrawHud(); DrawPerks(); break;
                case GameState.Results: DrawHud(); DrawResults(); break;
            }
        }

        // ---------------- helpers ----------------
        void Fill(Rect r, Color c)
        {
            var old = GUI.color;
            GUI.color = c;
            GUI.DrawTexture(r, tex);
            GUI.color = old;
        }

        void Bar(Rect r, float t, Color c, string text)
        {
            Fill(r, new Color(0f, 0f, 0f, 0.6f));
            Fill(new Rect(r.x + 2, r.y + 2, (r.width - 4) * Mathf.Clamp01(t), r.height - 4), c);
            GUI.Label(r, text, new GUIStyle(small) { alignment = TextAnchor.MiddleCenter, fontStyle = FontStyle.Bold });
        }

        bool Button(Rect r, string text, GUIStyle style, bool enabled = true, Color? tint = null)
        {
            var oldEnabled = GUI.enabled;
            var oldBg = GUI.backgroundColor;
            GUI.enabled = enabled;
            if (tint.HasValue) GUI.backgroundColor = tint.Value;
            bool pressed = GUI.Button(r, text, style);
            GUI.enabled = oldEnabled;
            GUI.backgroundColor = oldBg;
            return pressed;
        }

        // ---------------- screens ----------------
        void DrawGarage()
        {
            var s = gm.Save;
            Rect panel = new Rect(30, 30, 560, H - 60);
            Fill(panel, Panel);

            GUI.Label(new Rect(55, 40, 520, 70), "WASTELAND RUSH", title);
            GUI.Label(new Rect(58, 105, 520, 30),
                $"Stage <b>{s.stage}</b>   •   Goal <b>{Mathf.RoundToInt(LevelGenerator.GoalFor(s.stage))} m</b>   •   Best {Mathf.RoundToInt(s.bestDistance)} m", small);
            GUI.Label(new Rect(58, 132, 520, 36), $"<color=#FFD24A><b>$ {s.coins}</b></color>", header);

            float y = 180;
            for (int i = 0; i < Upgrades.Names.Length; i++)
            {
                var t = (UpgradeType)i;
                int lvl = gm.UpgradeLevel(t);
                Fill(new Rect(50, y, 520, 64), new Color(1f, 1f, 1f, 0.05f));
                GUI.Label(new Rect(62, y + 4, 300, 30), $"<b>{Upgrades.Names[i]}</b>", label);
                GUI.Label(new Rect(62, y + 32, 300, 26), Upgrades.Descs[i], small);
                for (int p = 0; p < Upgrades.MaxLevel; p++)
                    Fill(new Rect(250 + p * 22, y + 12, 18, 12), p < lvl ? Gold : new Color(1f, 1f, 1f, 0.15f));

                bool max = lvl >= Upgrades.MaxLevel;
                string txt = max ? "MAX" : $"$ {Upgrades.Cost(t, lvl)}";
                if (Button(new Rect(420, y + 12, 140, 40), txt, button, !max && gm.CanBuy(t), new Color(0.6f, 1f, 0.6f)))
                    gm.Buy(t);
                y += 72;
            }

            if (Button(new Rect(50, H - 125, 520, 70), "START RUN", bigButton, true, Accent))
                gm.StartRun();
            if (Button(new Rect(W - 150, H - 50, 130, 30), "Reset save", button, true, new Color(1f, 0.5f, 0.5f)))
                gm.ResetProgress();

            // Controls
            Rect help = new Rect(W - 360, 30, 330, 210);
            Fill(help, Panel);
            GUI.Label(new Rect(help.x + 16, help.y + 10, 300, 30), "<b>Controls</b>", header);
            GUI.Label(new Rect(help.x + 16, help.y + 50, 310, 160),
                "D / Right    Gas\nA / Left      Brake / reverse\nW / S         Tilt in the air\nSpace/Shift  Nitro boost\nR               Abandon run", label);
        }

        void DrawHud()
        {
            var car = gm.Car;
            var lv = gm.Level;
            if (car == null || lv == null) return;
            var st = car.stats;

            // progress bar
            float goal = lv.GoalDistance;
            Rect prog = new Rect(W * 0.2f, 16, W * 0.6f, 22);
            Bar(prog, gm.RunDistance / goal, Accent, $"{Mathf.RoundToInt(gm.RunDistance)} / {Mathf.RoundToInt(goal)} m");
            for (float d = LevelGenerator.PerkInterval; d < goal - 50f; d += LevelGenerator.PerkInterval)
                Fill(new Rect(prog.x + prog.width * (d / goal) - 2, prog.y - 4, 4, prog.height + 8), Gold);

            // left: bars
            Fill(new Rect(14, 50, 280, 128), Panel);
            Bar(new Rect(24, 60, 260, 24), car.Fuel / st.fuelMax, new Color(0.95f, 0.75f, 0.15f), $"FUEL  {Mathf.CeilToInt(car.Fuel)}");
            Bar(new Rect(24, 90, 260, 24), car.Hp / st.hpMax, new Color(0.85f, 0.2f, 0.2f), $"HULL  {Mathf.CeilToInt(car.Hp)}");
            Bar(new Rect(24, 120, 260, 24), car.Boost / st.boostMax, new Color(0.3f, 0.7f, 1f), "NITRO");
            GUI.Label(new Rect(24, 148, 260, 26), $"<b>{Mathf.RoundToInt(car.Speed * 3.6f)}</b> km/h", label);

            // right: stats
            Fill(new Rect(W - 234, 50, 220, 96), Panel);
            GUI.Label(new Rect(W - 220, 56, 200, 30), $"Kills: <b>{gm.RunKills}</b>", label);
            GUI.Label(new Rect(W - 220, 84, 200, 30), $"<color=#FFD24A>Kill $: <b>{gm.RunKillCoins}</b></color>", label);
            GUI.Label(new Rect(W - 220, 112, 200, 30), $"Stage {gm.Save.stage}", small);

            // perks
            if (st.perks.Count > 0)
            {
                float py = 160;
                GUI.Label(new Rect(W - 220, py, 210, 24), "<b>Perks</b>", small);
                foreach (var p in st.perks)
                {
                    py += 20;
                    GUI.Label(new Rect(W - 220, py, 210, 24), "• " + p, small);
                }
            }

            // toasts
            float ty = H * 0.28f;
            foreach (var t in gm.Toasts)
            {
                var c = new Color(1f, 1f, 1f, Mathf.Clamp01(t.time));
                var style = new GUIStyle(header) { alignment = TextAnchor.MiddleCenter };
                style.normal.textColor = new Color(Gold.r, Gold.g, Gold.b, c.a);
                GUI.Label(new Rect(0, ty, W, 36), t.text, style);
                ty += 36;
            }

            if (gm.State == GameState.Running && gm.RunDistance < 40f)
                GUI.Label(new Rect(0, H - 50, W, 30), "D = gas   A = brake   W/S = tilt   SPACE = nitro", center);
        }

        void DrawPerks()
        {
            Fill(new Rect(0, 0, W, H), new Color(0f, 0f, 0f, 0.55f));
            GUI.Label(new Rect(0, 110, W, 60), "SUPPLY DROP!", new GUIStyle(title) { alignment = TextAnchor.MiddleCenter });
            GUI.Label(new Rect(0, 170, W, 30), "Choose one upgrade for this run", center);

            var opts = gm.PerkOptions;
            float cw = 280, ch = 220, gap = 30;
            float total = opts.Count * cw + (opts.Count - 1) * gap;
            float x = (W - total) / 2f;
            for (int i = 0; i < opts.Count; i++)
            {
                Rect r = new Rect(x + i * (cw + gap), 240, cw, ch);
                if (Button(r, "", button, true, new Color(1f, 0.8f, 0.5f))) { gm.ChoosePerk(i); return; }
                Fill(new Rect(r.x + 6, r.y + 6, r.width - 12, r.height - 12), Panel);
                GUI.Label(new Rect(r.x + 10, r.y + 20, r.width - 20, 60), opts[i].name, cardTitle);
                GUI.Label(new Rect(r.x + 10, r.y + 90, r.width - 20, 100), opts[i].desc, cardText);
                GUI.Label(new Rect(r.x, r.y + ch - 34, r.width, 24), "click to choose", new GUIStyle(small) { alignment = TextAnchor.MiddleCenter });
            }
        }

        void DrawResults()
        {
            Fill(new Rect(0, 0, W, H), new Color(0f, 0f, 0f, 0.45f));
            Rect p = new Rect((W - 520) / 2f, 110, 520, 480);
            Fill(p, Panel);

            var t = new GUIStyle(title) { alignment = TextAnchor.MiddleCenter };
            if (gm.LastWin) t.normal.textColor = new Color(0.4f, 1f, 0.5f);
            GUI.Label(new Rect(p.x, p.y + 20, p.width, 64), gm.LastWin ? "EVACUATED!" : "RUN OVER", t);
            GUI.Label(new Rect(p.x, p.y + 88, p.width, 30), gm.LastReason, center);

            float y = p.y + 140;
            void Row(string a, string b)
            {
                GUI.Label(new Rect(p.x + 50, y, 300, 30), a, label);
                GUI.Label(new Rect(p.x + 300, y, 170, 30), b, new GUIStyle(label) { alignment = TextAnchor.UpperRight });
                y += 34;
            }
            Row("Distance", Mathf.RoundToInt(gm.RunDistance) + " m" + (gm.LastNewBest ? "  <color=#FFD24A>NEW BEST</color>" : ""));
            Row("Distance coins", "$ " + gm.LastDistanceCoins);
            Row($"Kills ({gm.RunKills})", "$ " + gm.RunKillCoins);
            if (gm.LastWin) Row($"Stage {gm.LastStage} bonus", "$ " + gm.LastWinBonus);
            Fill(new Rect(p.x + 50, y + 4, p.width - 100, 2), Gold);
            y += 14;
            Row("<b>Total earned</b>", $"<color=#FFD24A><b>$ {gm.LastEarned}</b></color>");
            if (gm.LastWin) GUI.Label(new Rect(p.x, y, p.width, 30), $"Stage {gm.Save.stage} unlocked — more zombies, longer road!", center);

            if (Button(new Rect(p.x + 40, p.yMax - 80, 210, 56), "GARAGE", button, true, new Color(0.6f, 1f, 0.6f)))
                gm.GoToGarage();
            if (Button(new Rect(p.xMax - 250, p.yMax - 80, 210, 56), "RUN AGAIN", button, true, Accent))
                gm.StartRun();
        }
    }
}
