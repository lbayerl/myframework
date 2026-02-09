# WebPush Notification Debugging Guide

## Problem Description

WebPush notifications showing "2 sent, 1 wrong" in production, but working in local dev (Chrome). Not working on Firefox.

## Changes Made

### 1. Added Comprehensive Logging

**Files Modified:**
- `packages/myframework-core/src/Push/Service/PushService.php`
- `packages/myframework-core/src/Push/Controller/NotificationController.php`

**What was added:**
- Detailed error logging for failed push notifications
- Logging of subscription/unsubscription events
- Test notification endpoint now returns error details
- All errors are logged with context (user ID, endpoint, reason)

### 2. Fixed Firefox Compatibility

**File Modified:**
- `packages/myframework-core/resources/views/notifications/index.html.twig`

**What was fixed:**
- Changed from `subscription.toJSON()` to explicit JSON construction
- Added fallback for different browser implementations
- Added console logging for debugging
- Better error handling with server response parsing

### 3. Improved UI Error Reporting

**What was improved:**
- Test notification now shows detailed errors in the UI
- Error details are logged to browser console
- Warning status shown when some notifications fail

## How to Debug in Production

### Step 1: Check Application Logs

After deploying these changes, check your application logs for push notification errors:

```bash
# Check Symfony logs
tail -f var/log/prod.log | grep -i "push\|notification"
```

Look for these log entries:
- `Push subscription expired, removing` - Subscription was removed (normal)
- `Push notification failed` - Contains endpoint and reason for failure
- `User subscribed to push notifications` - Successful subscription
- `Test notification sent` - Contains error details if any

### Step 2: Test in Browser Console

1. Navigate to `/notifications` in your production app
2. Open browser DevTools (F12)
3. Click "Enable Notifications"
4. Check Console for:
   ```
   Subscribing with data: {endpoint: "...", keys: {...}}
   ```
5. Click "Send Test Notification"
6. Check Console for error details if any fail

### Step 3: Check Error Reasons

Common error reasons you might see:

| Reason | Meaning | Solution |
|--------|---------|----------|
| `UnauthorizedRegistration` | VAPID keys mismatch | Regenerate VAPID keys for production |
| `NotFound` or `Gone` | Subscription expired | Normal - auto-deleted |
| `BadRequest` | Invalid subscription data | Check browser compatibility |
| `Unauthorized` | VAPID authentication failed | Verify VAPID_SUBJECT matches |
| `InvalidContent` | Payload format issue | Check notification payload |

### Step 4: Verify VAPID Configuration

Make sure production has valid VAPID keys set:

```bash
# In production .env.local
VAPID_PUBLIC_KEY=BNxxxxxxx...
VAPID_PRIVATE_KEY=xxxxxx...
VAPID_SUBJECT=mailto:your-production-email@example.com
```

**Important:** Production MUST use the same VAPID keys that were used when users subscribed!

If you regenerate keys, all existing subscriptions become invalid.

### Step 5: Check HTTPS Requirement

Service Workers and Push Notifications REQUIRE HTTPS in production (localhost is exempt).

Verify your production site:
- Is served over HTTPS
- Has a valid SSL certificate
- Service worker is registered correctly

Check in browser console:
```javascript
navigator.serviceWorker.getRegistration().then(reg => console.log(reg));
```

Should show: `ServiceWorkerRegistration {scope: "https://...", ...}`

### Step 6: Browser-Specific Debugging

**Firefox Issues:**
- Firefox has stricter Push API requirements
- Check Firefox DevTools > Application > Service Workers
- Verify permissions are granted (not just "default")
- Try unsubscribing and re-subscribing

**Chrome Issues:**
- Check chrome://serviceworker-internals/
- Verify no errors in service worker console
- Check Application > Manifest for valid config

### Step 7: Database Check

Verify subscriptions are stored correctly:

```sql
SELECT 
    u.email,
    ps.endpoint,
    ps.created_at,
    SUBSTRING(ps.endpoint, 1, 50) as endpoint_preview
FROM mf_push_subscription ps
JOIN mf_user u ON ps.user_id = u.id
ORDER BY ps.created_at DESC;
```

Look for:
- Multiple subscriptions per user (different browsers/devices - normal)
- Very old subscriptions (might be expired)
- Duplicate endpoints (shouldn't happen - unique constraint)

## Expected Behavior After Fixes

### In Logs

**Successful notification:**
```
[debug] Push notification sent successfully {"endpoint":"https://..."}
```

**Failed notification with details:**
```
[error] Push notification failed {"endpoint":"https://...","reason":"UnauthorizedRegistration","expired":false}
```

**Expired subscription:**
```
[info] Push subscription expired, removing {"endpoint":"https://..."}
```

### In Browser

**Test notification success:**
```
Status: ✅ Test notification sent! (2 sent, 0 failed)
Console: (no errors)
```

**Test notification partial failure:**
```
Status: ⚠️ Test notification sent! (1 sent, 1 failed)

Errors (check console for details):
- UnauthorizedRegistration

Console: 
Push notification errors: [{
  endpoint: "https://fcm.googleapis.com/...",
  reason: "UnauthorizedRegistration"
}]
```

## Common Production Issues

### Issue 1: "2 sent, 1 wrong" - Different VAPID Keys

**Symptom:** Some subscriptions work, others fail with `UnauthorizedRegistration`

**Cause:** User subscribed with different VAPID keys (e.g., staging vs production)

**Solution:**
1. Check logs for the failing endpoint
2. User needs to unsubscribe and re-subscribe
3. Or: Clear the database and have all users re-subscribe

### Issue 2: Firefox Not Working

**Symptom:** Chrome works, Firefox doesn't receive notifications

**Cause:** JSON serialization differences (fixed in this PR)

**Solution:**
1. Update to this PR version
2. Have Firefox users re-subscribe
3. Check Firefox console for errors

### Issue 3: No Notifications in Production

**Symptom:** Works on localhost, not in production

**Possible Causes:**
1. **Not HTTPS:** Service workers require HTTPS in production
2. **Wrong VAPID keys:** Production using different/invalid keys
3. **Service worker not registered:** Check browser DevTools
4. **Firewall/Proxy:** Blocking push endpoint URLs

**Debugging:**
```javascript
// In browser console on production site
navigator.serviceWorker.getRegistration().then(reg => {
  if (!reg) {
    console.error('No service worker registered!');
    return;
  }
  return reg.pushManager.getSubscription();
}).then(sub => {
  console.log('Current subscription:', sub);
  if (!sub) console.warn('Not subscribed to push!');
});
```

## Testing Checklist

- [ ] Verify VAPID keys are set in production `.env.local`
- [ ] Verify production site is HTTPS
- [ ] Check service worker is registered (browser DevTools)
- [ ] Subscribe to notifications in production
- [ ] Send test notification
- [ ] Check application logs for errors
- [ ] Check browser console for errors
- [ ] Test in both Chrome and Firefox
- [ ] Verify database contains subscription
- [ ] Test notification click (opens correct URL)

## Additional Resources

- [Web Push Protocol RFC](https://datatracker.ietf.org/doc/html/rfc8030)
- [VAPID Specification](https://datatracker.ietf.org/doc/html/rfc8292)
- [MDN: Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API)
- [Service Worker Debugging](https://developer.chrome.com/docs/workbox/troubleshooting-and-logging/)

## Need More Help?

If issues persist after these debugging steps:

1. **Capture full logs:**
   - Application logs with timestamps
   - Browser console output (screenshots)
   - Network tab (subscription POST requests)

2. **Check database state:**
   - How many subscriptions exist?
   - Do they have valid endpoints?
   - When were they created?

3. **Test with a fresh user:**
   - Create new account
   - Subscribe from scratch
   - Check if it works

4. **Compare environments:**
   - Does it work in staging?
   - What's different in production?
   - VAPID keys, URLs, certificates?
