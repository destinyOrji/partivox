# 🐦 Twitter OAuth Setup Guide for PartiVox

## 🚨 Current Issue
The Twitter login is redirecting to `127.0.0.1` instead of your Render domain. This happens because:

1. **Twitter App Configuration** - Your Twitter app's callback URL is set to localhost
2. **Missing Environment Variables** - Twitter API credentials not set in Render

## ✅ Solution Steps

### Step 1: Configure Twitter App
1. Go to https://developer.twitter.com/en/portal/dashboard
2. Select your app (or create a new one)
3. Go to **App Settings** → **Authentication settings**
4. Set **Callback URL** to: `https://partivox-1.onrender.com/api/twitter/twitter-auth.php`
5. Enable **OAuth 1.0a** authentication
6. Save changes

### Step 2: Get Twitter API Credentials
1. In your Twitter app dashboard
2. Go to **Keys and tokens** tab
3. Copy:
   - **API Key** (Consumer Key)
   - **API Key Secret** (Consumer Secret)

### Step 3: Set Environment Variables in Render
In your Render dashboard, add these environment variables:

```
TWITTER_CONSUMER_KEY=your_api_key_here
TWITTER_CONSUMER_SECRET=your_api_secret_here
TWITTER_OAUTH_CALLBACK=https://partivox-1.onrender.com/api/twitter/twitter-auth.php
```

### Step 4: Redeploy
1. Save environment variables in Render
2. Render will automatically redeploy
3. Test Twitter login

## 🔧 Alternative: Simple Email Login

If you don't want to set up Twitter OAuth right now, I can create a simple email-based login system that works immediately.

## 🧪 Test Your Fix

After setting up Twitter OAuth:
1. Visit `https://partivox-1.onrender.com`
2. Click "Connect with X"
3. Should redirect to Twitter (not localhost)
4. After Twitter auth, should redirect back to your dashboard

## 🚨 Common Issues

### Issue: "This site can't be reached 127.0.0.1"
**Cause**: Twitter app callback URL is set to localhost
**Fix**: Update Twitter app callback URL to your Render domain

### Issue: "Invalid callback URL"
**Cause**: Callback URL doesn't match what's configured in Twitter app
**Fix**: Ensure both match exactly: `https://partivox-1.onrender.com/api/twitter/twitter-auth.php`

### Issue: "App not found"
**Cause**: Missing or incorrect API credentials
**Fix**: Check environment variables in Render dashboard

## 🎯 Quick Fix Option

If you want to test the app immediately without Twitter setup, I can create a simple email login system. Let me know!
