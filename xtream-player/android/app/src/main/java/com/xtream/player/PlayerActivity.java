package com.xtream.player;

import android.app.Activity;
import android.content.Intent;
import android.graphics.Color;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.util.TypedValue;
import android.view.Gravity;
import android.view.KeyEvent;
import android.view.ViewGroup;
import android.view.WindowManager;
import android.widget.FrameLayout;
import android.widget.TextView;
import android.widget.Toast;
import androidx.annotation.NonNull;
import androidx.annotation.OptIn;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import androidx.media3.common.C;
import androidx.media3.common.MediaItem;
import androidx.media3.common.MediaMetadata;
import androidx.media3.common.MimeTypes;
import androidx.media3.common.PlaybackException;
import androidx.media3.common.Player;
import androidx.media3.common.util.UnstableApi;
import androidx.media3.datasource.DefaultHttpDataSource;
import androidx.media3.exoplayer.ExoPlayer;
import androidx.media3.exoplayer.source.DefaultMediaSourceFactory;
import androidx.media3.ui.PlayerView;
import java.util.ArrayList;
import java.util.List;

/** مشغل فيديو أصلي يدعم m3u8 و ts و mp4 و mkv مع التنقل بين القنوات */
@OptIn(markerClass = UnstableApi.class)
public class PlayerActivity extends Activity {
    public static final String EXTRA_URLS = "urls";
    public static final String EXTRA_TITLES = "titles";
    public static final String EXTRA_INDEX = "index";
    public static final String EXTRA_START = "start";
    public static final String EXTRA_LIVE = "live";

    private static final String UA =
        "Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36";

    private ExoPlayer player;
    private PlayerView playerView;
    private TextView titleView;
    private ArrayList<String> titles;
    private boolean live;
    private int retries = 0;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private final Runnable hideTitle = () -> { if (titleView != null) titleView.setAlpha(0f); };

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        hideSystemUi();

        Intent in = getIntent();
        ArrayList<String> urls = in.getStringArrayListExtra(EXTRA_URLS);
        titles = in.getStringArrayListExtra(EXTRA_TITLES);
        if (urls == null || urls.isEmpty()) { finish(); return; }
        if (titles == null) titles = new ArrayList<>();
        int index = Math.max(0, Math.min(in.getIntExtra(EXTRA_INDEX, 0), urls.size() - 1));
        long start = in.getLongExtra(EXTRA_START, 0);
        live = in.getBooleanExtra(EXTRA_LIVE, false);

        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.BLACK);
        playerView = new PlayerView(this);
        root.addView(playerView, new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        titleView = new TextView(this);
        titleView.setTextColor(Color.WHITE);
        titleView.setTextSize(TypedValue.COMPLEX_UNIT_SP, 20);
        titleView.setShadowLayer(6f, 0f, 0f, Color.BLACK);
        int pad = (int) (24 * getResources().getDisplayMetrics().density);
        titleView.setPadding(pad, pad, pad, pad);
        root.addView(titleView, new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT, Gravity.TOP | Gravity.START));
        setContentView(root);

        DefaultHttpDataSource.Factory http = new DefaultHttpDataSource.Factory()
            .setUserAgent(UA)
            .setAllowCrossProtocolRedirects(true)
            .setConnectTimeoutMs(15000)
            .setReadTimeoutMs(20000);

        player = new ExoPlayer.Builder(this)
            .setMediaSourceFactory(new DefaultMediaSourceFactory(this).setDataSourceFactory(http))
            .build();
        playerView.setPlayer(player);
        playerView.setShowNextButton(urls.size() > 1);
        playerView.setShowPreviousButton(urls.size() > 1);
        playerView.setControllerShowTimeoutMs(3500);

        List<MediaItem> items = new ArrayList<>();
        for (int i = 0; i < urls.size(); i++) {
            String url = urls.get(i);
            MediaItem.Builder b = new MediaItem.Builder()
                .setUri(url)
                .setMediaMetadata(new MediaMetadata.Builder().setTitle(titleAt(i)).build());
            if (url.toLowerCase().contains(".m3u8")) b.setMimeType(MimeTypes.APPLICATION_M3U8);
            items.add(b.build());
        }
        player.setMediaItems(items, index, live ? C.TIME_UNSET : start);
        player.addListener(new Player.Listener() {
            @Override
            public void onMediaItemTransition(MediaItem mediaItem, int reason) {
                retries = 0;
                showTitle();
            }

            @Override
            public void onPlayerError(@NonNull PlaybackException error) {
                if (error.errorCode == PlaybackException.ERROR_CODE_BEHIND_LIVE_WINDOW) {
                    player.seekToDefaultPosition();
                    player.prepare();
                    return;
                }
                if (retries < 3) {
                    retries++;
                    handler.postDelayed(() -> {
                        if (player != null) { player.prepare(); player.play(); }
                    }, 1500L * retries);
                    return;
                }
                Toast.makeText(PlayerActivity.this,
                    "تعذر التشغيل: " + error.getErrorCodeName(), Toast.LENGTH_LONG).show();
            }
        });
        player.prepare();
        player.play();
        showTitle();
    }

    private String titleAt(int i) {
        return i < titles.size() ? titles.get(i) : "";
    }

    private void showTitle() {
        if (player == null) return;
        titleView.setText(titleAt(player.getCurrentMediaItemIndex()));
        titleView.setAlpha(1f);
        handler.removeCallbacks(hideTitle);
        handler.postDelayed(hideTitle, 4000);
    }

    private void hideSystemUi() {
        WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
        WindowInsetsControllerCompat c = WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        c.hide(WindowInsetsCompat.Type.systemBars());
        c.setSystemBarsBehavior(WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE);
    }

    @Override
    public boolean dispatchKeyEvent(KeyEvent event) {
        if (player != null && event.getAction() == KeyEvent.ACTION_DOWN && player.getMediaItemCount() > 1) {
            int k = event.getKeyCode();
            boolean controllerHidden = !playerView.isControllerFullyVisible();
            if (k == KeyEvent.KEYCODE_CHANNEL_UP || (live && controllerHidden && k == KeyEvent.KEYCODE_DPAD_UP)) {
                zap(1);
                return true;
            }
            if (k == KeyEvent.KEYCODE_CHANNEL_DOWN || (live && controllerHidden && k == KeyEvent.KEYCODE_DPAD_DOWN)) {
                zap(-1);
                return true;
            }
        }
        return super.dispatchKeyEvent(event);
    }

    private void zap(int d) {
        int n = player.getMediaItemCount();
        int i = ((player.getCurrentMediaItemIndex() + d) % n + n) % n;
        player.seekToDefaultPosition(i);
        player.prepare();
        player.play();
    }

    @Override
    public void finish() {
        if (player != null) {
            Intent data = new Intent();
            data.putExtra("position", Math.max(0, player.getCurrentPosition()));
            long dur = player.getDuration();
            data.putExtra("duration", dur == C.TIME_UNSET ? 0 : dur);
            data.putExtra("index", player.getCurrentMediaItemIndex());
            setResult(RESULT_OK, data);
        }
        super.finish();
    }

    @Override
    protected void onPause() {
        super.onPause();
        if (player != null) player.pause();
    }

    @Override
    protected void onResume() {
        super.onResume();
        hideSystemUi();
        if (player != null) player.play();
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        if (player != null) {
            player.release();
            player = null;
        }
        super.onDestroy();
    }
}
