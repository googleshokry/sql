using System.Collections.Generic;
using UnityEngine;
using UnityEngine.Rendering;
#if !ENABLE_LEGACY_INPUT_MANAGER && ENABLE_INPUT_SYSTEM
using UnityEngine.InputSystem;
#endif

namespace WastelandRush
{
    /// <summary>
    /// We only use Unity's built-in layers so the project needs no TagManager setup.
    /// </summary>
    public static class Layers
    {
        public const int Ground = 0;   // "Default"        -> terrain (wheel raycasts hit only this)
        public const int Props = 1;    // "TransparentFX"  -> zombies, crates, pickups
        public const int Car = 2;      // "Ignore Raycast" -> the player car
        public const int GroundMask = 1 << Ground;
    }

    public static class Util
    {
        static Shader litShader;
        static Shader unlitShader;
        static readonly Dictionary<Color, Material> litCache = new Dictionary<Color, Material>();
        static readonly Dictionary<Color, Material> unlitCache = new Dictionary<Color, Material>();

        /// <summary>Shared lit material for a color (works in Built-in and URP).</summary>
        public static Material Mat(Color c)
        {
            if (litCache.TryGetValue(c, out var m) && m != null) return m;
            if (litShader == null)
            {
                if (GraphicsSettings.currentRenderPipeline != null)
                    litShader = Shader.Find("Universal Render Pipeline/Lit");
                if (litShader == null) litShader = Shader.Find("Standard");
            }
            m = new Material(litShader) { color = c };
            litCache[c] = m;
            return m;
        }

        public static Material Unlit(Color c)
        {
            if (unlitCache.TryGetValue(c, out var m) && m != null) return m;
            if (unlitShader == null) unlitShader = Shader.Find("Sprites/Default");
            m = new Material(unlitShader) { color = c };
            unlitCache[c] = m;
            return m;
        }

        public static GameObject Prim(PrimitiveType type, Transform parent, Vector3 localPos, Vector3 scale, Color color, bool keepCollider = false)
        {
            var go = GameObject.CreatePrimitive(type);
            go.transform.SetParent(parent, false);
            go.transform.localPosition = localPos;
            go.transform.localScale = scale;
            go.GetComponent<Renderer>().sharedMaterial = Mat(color);
            if (!keepCollider) Object.DestroyImmediate(go.GetComponent<Collider>());
            return go;
        }

        public static void SetLayerRecursive(GameObject go, int layer)
        {
            go.layer = layer;
            foreach (Transform t in go.transform) SetLayerRecursive(t.gameObject, layer);
        }

        // ---- Rigidbody API differences between Unity 2021/2022 and Unity 6 ----
        public static Vector3 GetVel(this Rigidbody rb)
        {
#if UNITY_6000_0_OR_NEWER
            return rb.linearVelocity;
#else
            return rb.velocity;
#endif
        }

        public static void SetVel(this Rigidbody rb, Vector3 v)
        {
#if UNITY_6000_0_OR_NEWER
            rb.linearVelocity = v;
#else
            rb.velocity = v;
#endif
        }

        public static void SetDamping(this Rigidbody rb, float linear, float angular)
        {
#if UNITY_6000_0_OR_NEWER
            rb.linearDamping = linear;
            rb.angularDamping = angular;
#else
            rb.drag = linear;
            rb.angularDrag = angular;
#endif
        }
    }

    /// <summary>Works with the old Input Manager and with the new Input System package.</summary>
    public static class GameInput
    {
        /// <summary>+1 gas, -1 brake / reverse.</summary>
        public static float Drive => (Right ? 1f : 0f) - (Left ? 1f : 0f);

        /// <summary>+1 nose up, -1 nose down.</summary>
        public static float Tilt => (Up ? 1f : 0f) - (Down ? 1f : 0f);

#if ENABLE_LEGACY_INPUT_MANAGER
        static bool Right => Input.GetKey(KeyCode.D) || Input.GetKey(KeyCode.RightArrow);
        static bool Left => Input.GetKey(KeyCode.A) || Input.GetKey(KeyCode.LeftArrow);
        static bool Up => Input.GetKey(KeyCode.W) || Input.GetKey(KeyCode.UpArrow);
        static bool Down => Input.GetKey(KeyCode.S) || Input.GetKey(KeyCode.DownArrow);
        public static bool Boost => Input.GetKey(KeyCode.Space) || Input.GetKey(KeyCode.LeftShift);
        public static bool AbortPressed => Input.GetKeyDown(KeyCode.R);
#elif ENABLE_INPUT_SYSTEM
        static Keyboard K => Keyboard.current;
        static bool Right => K != null && (K.dKey.isPressed || K.rightArrowKey.isPressed);
        static bool Left => K != null && (K.aKey.isPressed || K.leftArrowKey.isPressed);
        static bool Up => K != null && (K.wKey.isPressed || K.upArrowKey.isPressed);
        static bool Down => K != null && (K.sKey.isPressed || K.downArrowKey.isPressed);
        public static bool Boost => K != null && (K.spaceKey.isPressed || K.leftShiftKey.isPressed);
        public static bool AbortPressed => K != null && K.rKey.wasPressedThisFrame;
#else
        static bool Right => false;
        static bool Left => false;
        static bool Up => false;
        static bool Down => false;
        public static bool Boost => false;
        public static bool AbortPressed => false;
#endif
    }
}
