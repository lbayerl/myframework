# Production Log Examples - WebPush Notifications

## What to Look For in Production Logs

After deploying the WebPush notification fixes, you'll see detailed log entries that help diagnose issues. Here's what to expect:

## Successful Subscription

```
[2026-02-09 12:34:56] app.INFO: User subscribed to push notifications {
    "user_id": 123,
    "endpoint": "https://fcm.googleapis.com/fcm/send/ABC123..."
}
```

**What this means**: A user successfully subscribed to push notifications. The endpoint is the unique identifier for their device/browser.

## Successful Notification Send

```
[2026-02-09 12:35:10] app.DEBUG: Push notification sent successfully {
    "endpoint": "https://fcm.googleapis.com/fcm/send/ABC123..."
}
```

**What this means**: A notification was successfully delivered to the push service (FCM, Mozilla Push, etc.). This doesn't guarantee the user saw it, but it was accepted by the push service.

## Failed Notification - VAPID Key Mismatch

```
[2026-02-09 12:35:10] app.ERROR: Push notification failed {
    "endpoint": "https://fcm.googleapis.com/fcm/send/XYZ789...",
    "reason": "UnauthorizedRegistration",
    "expired": false
}
```

**What this means**: The VAPID keys used to send the notification don't match the keys used when the user subscribed. This typically happens when:
- VAPID keys were regenerated after user subscribed
- Different VAPID keys in staging vs production
- User subscribed in one environment but receiving notifications from another

**Solution**: User needs to unsubscribe and re-subscribe with the current VAPID keys.

## Failed Notification - Expired Subscription

```
[2026-02-09 12:35:10] app.INFO: Push subscription expired, removing {
    "endpoint": "https://updates.push.services.mozilla.com/wpush/..."
}
```

**What this means**: The subscription is no longer valid (user uninstalled browser, cleared data, etc.). The system automatically removes it from the database.

**Solution**: This is normal behavior. No action needed. User will need to re-subscribe if they want notifications again.

## Test Notification Results

```
[2026-02-09 12:36:00] app.INFO: Test notification sent {
    "user_id": 123,
    "success_count": 2,
    "error_count": 1,
    "total": 3,
    "errors": [
        {
            "endpoint": "https://fcm.googleapis.com/fcm/send/OLD123...",
            "reason": "UnauthorizedRegistration"
        }
    ]
}
```

**What this means**: The user has 3 subscriptions (3 devices/browsers), 2 worked, 1 failed.

**Analysis**:
- User probably has subscriptions from multiple devices (phone, laptop, etc.)
- One subscription is invalid (old VAPID keys or expired)
- That subscription will be auto-removed on next failed attempt if it's expired

## Subscription Error

```
[2026-02-09 12:37:00] app.ERROR: Failed to subscribe to push notifications {
    "user_id": 123,
    "error_class": "Doctrine\\DBAL\\Exception\\UniqueConstraintViolationException",
    "error_message": "Duplicate entry '...' for key 'UNIQ_ENDPOINT'"
}
```

**What this means**: User tried to subscribe but this endpoint already exists in the database.

**Analysis**: This is actually normal - the code checks for existing subscriptions and returns them. This error shouldn't happen with current code, but if it does, it's not critical.

## Unsubscribe

```
[2026-02-09 12:38:00] app.INFO: User unsubscribed from push notifications {
    "endpoint": "https://fcm.googleapis.com/fcm/send/ABC123..."
}
```

**What this means**: User clicked "Disable Notifications" or programmatically unsubscribed.

## Common Error Reasons Reference

### UnauthorizedRegistration
- **Cause**: VAPID key mismatch
- **Frequency**: Common after VAPID key regeneration
- **Action**: User re-subscribe required
- **Auto-fix**: No (user action needed)

### NotFound (404)
- **Cause**: Subscription doesn't exist on push service
- **Frequency**: Occasional (user uninstalled, cleared data)
- **Action**: None needed
- **Auto-fix**: Yes (auto-removed from database)

### Gone (410)
- **Cause**: Subscription expired
- **Frequency**: Occasional (inactive users)
- **Action**: None needed
- **Auto-fix**: Yes (auto-removed from database)

### BadRequest (400)
- **Cause**: Invalid notification payload or subscription format
- **Frequency**: Rare (should not happen with current code)
- **Action**: Check notification data format
- **Auto-fix**: No (code fix needed)

### TooManyRequests (429)
- **Cause**: Rate limiting by push service
- **Frequency**: Rare
- **Action**: Reduce notification frequency
- **Auto-fix**: No (behavior change needed)

## Browser Console Logs

Users can also check their browser console (F12) for client-side logs:

### Service Worker Logs

```
[SW] Push notification received: {title: "Test", body: "This is a test...", url: "/notifications"}
[SW] Notification shown: Test Notification
```

**What this means**: The service worker received and displayed the notification.

### Subscription Logs

```
Subscribing with data: {
    endpoint: "https://fcm.googleapis.com/fcm/send/...",
    keys: {auth: "...", p256dh: "..."}
}
```

**What this means**: The browser is sending subscription data to the server. All required fields are present.

### Subscription Error

```
❌ Failed to enable notifications: Invalid subscription data: missing keys.p256dh
```

**What this means**: The subscription data is incomplete. This indicates a browser compatibility issue.

**Solution**: This should not happen with the Firefox fix, but if it does, check browser console for more details.

## Monitoring Commands

### Follow logs in real-time
```bash
tail -f var/log/prod.log | grep -i "push\|notification"
```

### Find all push-related errors today
```bash
grep -i "push.*error" var/log/prod.log | grep "$(date +%Y-%m-%d)"
```

### Count subscription activity
```bash
grep "subscribed to push notifications" var/log/prod.log | wc -l
```

### Find VAPID key errors
```bash
grep "UnauthorizedRegistration" var/log/prod.log
```

### Check expired subscriptions
```bash
grep "Push subscription expired" var/log/prod.log
```

## Database Queries

### See all active subscriptions
```sql
SELECT 
    u.email,
    COUNT(ps.id) as subscription_count,
    MAX(ps.created_at) as latest_subscription
FROM mf_user u
LEFT JOIN mf_push_subscription ps ON u.id = ps.user_id
GROUP BY u.id, u.email
HAVING subscription_count > 0
ORDER BY latest_subscription DESC;
```

### Find users with multiple subscriptions
```sql
SELECT 
    u.email,
    COUNT(ps.id) as device_count
FROM mf_user u
JOIN mf_push_subscription ps ON u.id = ps.user_id
GROUP BY u.id, u.email
HAVING device_count > 1
ORDER BY device_count DESC;
```

### Check for old subscriptions (> 90 days)
```sql
SELECT 
    u.email,
    ps.created_at,
    DATEDIFF(NOW(), ps.created_at) as age_days,
    SUBSTRING(ps.endpoint, 1, 50) as endpoint_preview
FROM mf_push_subscription ps
JOIN mf_user u ON ps.user_id = u.id
WHERE ps.created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
ORDER BY ps.created_at ASC;
```

## Alerting Recommendations

### Critical: High failure rate
```bash
# Alert if > 50% of test notifications fail
# Check logs for pattern of UnauthorizedRegistration errors
```

**Action**: Likely VAPID key mismatch. Verify production VAPID keys are correct.

### Warning: Many expired subscriptions
```bash
# Alert if > 10 expired subscriptions in 1 hour
```

**Action**: Normal if users are uninstalling or clearing data. Monitor for patterns.

### Info: No subscriptions
```bash
# Alert if total subscriptions = 0
```

**Action**: Check if notifications feature is discoverable. May need user education.

## Success Metrics

After deployment, you should see:
- ✅ Error rate < 10% (some failures are normal for expired subs)
- ✅ Clear error reasons in logs for all failures
- ✅ Expired subscriptions auto-removed
- ✅ Firefox users can subscribe
- ✅ Test notifications return detailed error information

## Quick Diagnosis Decision Tree

```
Test notification shows "2 sent, 1 failed"
    ↓
Check logs for error reason
    ↓
UnauthorizedRegistration?
    ↓ YES
    VAPID key mismatch - check production .env.local
    ↓ NO
    ↓
Gone/NotFound?
    ↓ YES
    Normal - subscription expired (auto-removed)
    ↓ NO
    ↓
BadRequest?
    ↓ YES
    Check notification payload format
    ↓ NO
    ↓
Check WEBPUSH_DEBUGGING.md for other errors
```
