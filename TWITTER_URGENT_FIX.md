# 🚨 URGENT: Twitter Callback URL Fix Required

## ❌ **Current Error:**
```
Error Code 415: "Callback URL not approved for this client application"
```

## 🔍 **Root Cause:**
Your Twitter app is still configured with the old callback URL:
- **Current (Wrong)**: `http://127.0.0.1:8000/api/twitter/twitter-auth.php`
- **Should be**: `https://partivox-1.onrender.com/api/twitter/twitter-auth.php`

## ✅ **IMMEDIATE FIX REQUIRED:**

### **Step 1: Update Twitter Developer Dashboard**
1. **Go to**: https://developer.twitter.com/en/portal/dashboard
2. **Select your app**
3. **Go to**: **App Settings** → **Authentication settings**
4. **Change Callback URL from**:
   ```
   http://127.0.0.1:8000/api/twitter/twitter-auth.php
   ```
   **To**:
   ```
   https://partivox-1.onrender.com/api/twitter/twitter-auth.php
   ```
5. **Save changes**

### **Step 2: Add Environment Variables to Render**
In your Render dashboard, add these environment variables:

```
TWITTER_CONSUMER_KEY=your_api_key_here
TWITTER_CONSUMER_SECRET=your_api_secret_here
TWITTER_OAUTH_CALLBACK=https://partivox-1.onrender.com/api/twitter/twitter-auth.php
```

### **Step 3: Test**
After making these changes:
1. Wait 2-3 minutes for Render to redeploy
2. Visit `https://partivox-1.onrender.com`
3. Click "Connect with X"
4. Should work without the 415 error

## 🎯 **Why This Happens:**
- Twitter validates the callback URL against what's configured in your app
- If they don't match exactly, you get error 415
- The URL must be HTTPS and match your actual domain

## 🚀 **Alternative: Use Email Login**
If you want to test immediately without Twitter setup:
- Use the email login system at `/api/auth/email-login.php`
- Works right now without any external configuration

**The fix is simple - just update that one URL in your Twitter app settings!** 🎉
