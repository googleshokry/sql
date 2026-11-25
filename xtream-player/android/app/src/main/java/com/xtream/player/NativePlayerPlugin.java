package com.xtream.player;

import android.content.Intent;
import androidx.activity.result.ActivityResult;
import com.getcapacitor.JSArray;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.ActivityCallback;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.util.ArrayList;
import org.json.JSONException;
import org.json.JSONObject;

/** يفتح المشغل الأصلي (ExoPlayer) من جافاسكربت: NativePlayer.play({items, index, startPosition, live}) */
@CapacitorPlugin(name = "NativePlayer")
public class NativePlayerPlugin extends Plugin {

    @PluginMethod
    public void play(PluginCall call) {
        JSArray items = call.getArray("items");
        ArrayList<String> urls = new ArrayList<>();
        ArrayList<String> titles = new ArrayList<>();
        try {
            for (int i = 0; items != null && i < items.length(); i++) {
                JSONObject o = items.getJSONObject(i);
                urls.add(o.getString("url"));
                titles.add(o.optString("title", ""));
            }
        } catch (JSONException e) {
            call.reject("Invalid items");
            return;
        }
        if (urls.isEmpty()) {
            call.reject("No items to play");
            return;
        }
        Intent intent = new Intent(getContext(), PlayerActivity.class);
        intent.putStringArrayListExtra(PlayerActivity.EXTRA_URLS, urls);
        intent.putStringArrayListExtra(PlayerActivity.EXTRA_TITLES, titles);
        intent.putExtra(PlayerActivity.EXTRA_INDEX, call.getInt("index", 0));
        intent.putExtra(PlayerActivity.EXTRA_START, call.getDouble("startPosition", 0.0).longValue());
        intent.putExtra(PlayerActivity.EXTRA_LIVE, call.getBoolean("live", false));
        startActivityForResult(call, intent, "onPlayerResult");
    }

    @ActivityCallback
    private void onPlayerResult(PluginCall call, ActivityResult result) {
        if (call == null) return;
        JSObject ret = new JSObject();
        Intent data = result.getData();
        if (data != null) {
            ret.put("position", data.getLongExtra("position", 0));
            ret.put("duration", data.getLongExtra("duration", 0));
            ret.put("index", data.getIntExtra("index", 0));
        }
        call.resolve(ret);
    }
}
