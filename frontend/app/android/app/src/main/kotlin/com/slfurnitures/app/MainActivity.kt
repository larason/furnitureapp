package com.slfurnitures.app

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.os.Build
import io.flutter.embedding.android.FlutterActivity

class MainActivity : FlutterActivity() {
    override fun onCreate(savedInstanceState: android.os.Bundle?) {
        super.onCreate(savedInstanceState)
        ensureDefaultNotificationChannel()
    }

    /**
     * Creates the channel declared as the FCM default in the manifest.
     *
     * Declaring the id is only half of the contract: on Android O and later a
     * message addressed to a channel that does not exist is dropped rather than
     * shown. Creating it here means the channel id, name, and importance are
     * owned by the application instead of the platform fallback channel.
     *
     * Idempotent: creating an existing channel is a no-op, and the default
     * importance is not changed on later launches.
     */
    private fun ensureDefaultNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (manager.getNotificationChannel(DEFAULT_CHANNEL_ID) != null) return

        val channel = NotificationChannel(
            DEFAULT_CHANNEL_ID,
            getString(R.string.default_notification_channel_name),
            NotificationManager.IMPORTANCE_DEFAULT,
        ).apply {
            description = getString(R.string.default_notification_channel_description)
        }

        manager.createNotificationChannel(channel)
    }

    private companion object {
        /** Must match `default_notification_channel_id` in the manifest. */
        const val DEFAULT_CHANNEL_ID = "fcm_default_channel"
    }
}
