using UnityEngine;

namespace WastelandRush
{
    /// <summary>Side-view camera that leads ahead of the car and zooms out with speed.</summary>
    public class CameraFollow : MonoBehaviour
    {
        Transform target;
        Rigidbody targetRb;
        Vector3 velocity;
        Vector3 lookPoint;

        public void SetTarget(Transform t)
        {
            target = t;
            targetRb = t != null ? t.GetComponent<Rigidbody>() : null;
            if (t == null) return;
            transform.position = t.position + new Vector3(3f, 3.5f, -16f);
            lookPoint = t.position + new Vector3(3f, 1f, 0f);
            transform.rotation = Quaternion.LookRotation(lookPoint - transform.position);
        }

        void LateUpdate()
        {
            if (target == null) return;
            float vx = targetRb != null ? targetRb.GetVel().x : 0f;
            float lead = Mathf.Clamp(vx * 0.35f, -3f, 9f) + 2f;
            float dist = 16f + Mathf.Clamp(Mathf.Abs(vx) * 0.3f, 0f, 9f);

            Vector3 desired = target.position + new Vector3(lead, 3.5f + dist * 0.08f, -dist);
            transform.position = Vector3.SmoothDamp(transform.position, desired, ref velocity, 0.25f, Mathf.Infinity, Time.unscaledDeltaTime);
            lookPoint = Vector3.Lerp(lookPoint, target.position + new Vector3(lead, 1f, 0f), 1f - Mathf.Exp(-8f * Time.unscaledDeltaTime));
            transform.rotation = Quaternion.LookRotation(lookPoint - transform.position);
        }
    }
}
