# PARTIVOX API Documentation

## 🚀 Complete API Reference

### Base URL
```
http://localhost:8000/api/
```

### Authentication
All protected endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer YOUR_JWT_TOKEN
```

---

## 🔐 Authentication Endpoints

### 1. User Registration
**POST** `/auth/register`
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123"
}
```

### 2. Email Login
**POST** `/auth/login`
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

### 3. OTP Verification
**POST** `/auth/verify-otp`
```json
{
  "email": "john@example.com",
  "otp": "123456"
}
```

### 4. Twitter Authentication
**GET** `/twitter/twitter-auth.php`
- Redirects to Twitter OAuth flow

### 5. Google Authentication
**POST** `/auth/google`
```json
{
  "code": "google_auth_code"
}
```

---

## 👤 User Management

### 1. Get User Profile
**GET** `/user/me.php`
- Returns current user information

### 2. Update Profile
**PUT** `/settings/profile`
```json
{
  "name": "Updated Name",
  "bio": "Updated bio"
}
```

---

## 💎 Wallet & Transactions

### 1. Get Wallet Balance
**GET** `/wallet/balance`
```json
Response:
{
  "status": "success",
  "data": {
    "diamonds": 1500,
    "usdt": 25.50
  }
}
```

### 2. Buy Diamonds
**POST** `/wallet/buy`
```json
{
  "quantity": 100
}
```

### 3. Convert Diamonds to USDT
**POST** `/wallet/convert`
```json
{
  "diamonds": 100
}
```

### 4. Withdraw USDT
**POST** `/wallet/withdraw`
```json
{
  "amount": 10.00,
  "to": "wallet_address"
}
```

### 5. Get Transaction History
**GET** `/wallet/transactions?page=1&limit=10&type=buy_diamonds`

### 6. Confirm On-chain Purchase
**POST** `/wallet/onchain/evm/confirm`
```json
{
  "txHash": "0x...",
  "usdtAmount": 10.00
}
```

---

## 🎯 Campaign Management

### 1. Create Campaign
**POST** `/campaigns/upload`
```json
{
  "title": "NFT Airdrop Campaign",
  "description": "Join our exclusive NFT drop",
  "start_date": "2024-01-01",
  "end_date": "2024-01-31",
  "budget": 1000,
  "target_audience": [],
  "assets": [],
  "status": "draft"
}
```

### 2. Get Campaign Details
**GET** `/campaigns/view?id=CAMPAIGN_ID`

### 3. Get Campaign Progress
**GET** `/campaigns/progress?id=CAMPAIGN_ID`

### 4. List User Campaigns
**GET** `/campaigns/list?page=1&limit=10&status=active&search=nft`

---

## ✅ Task Management

### 1. List Available Tasks
**GET** `/tasks/list?status=active&campaign_id=CAMPAIGN_ID&page=1&limit=10`

### 2. Update Task Status
**PUT** `/tasks/status`
```json
{
  "id": "TASK_ID",
  "status": "completed",
  "notes": "Task completed successfully"
}
```

---

## ⚙️ Settings

### 1. Get User Settings
**GET** `/settings/get`

### 2. Update Settings
**PUT** `/settings/update`
```json
{
  "notifications": {
    "email": true,
    "push": false,
    "sms": false
  },
  "preferences": {
    "theme": "dark",
    "timezone": "UTC"
  }
}
```

### 3. Get Notification Settings
**GET** `/settings/notifications`

### 4. Update Notification Settings
**PUT** `/settings/notifications`
```json
{
  "email_notifications": true,
  "push_notifications": true,
  "campaign_updates": true,
  "task_reminders": false
}
```

### 5. Get Privacy Settings
**GET** `/settings/privacy`

### 6. Update Privacy Settings
**PUT** `/settings/privacy`
```json
{
  "profile_visibility": "public",
  "show_activity": true,
  "allow_messages": true
}
```

---

## 🛡️ Admin Endpoints

### Dashboard Statistics
**GET** `/admin/dashboard`
```json
Response:
{
  "status": "success",
  "data": {
    "total_users": 150,
    "email_users": 120,
    "twitter_users": 30,
    "today_users": 5,
    "recent_users": 25,
    "verified_users": 140,
    "unverified_users": 10,
    "active_campaigns": 8,
    "total_revenue": 5000,
    "pending_requests": 3
  }
}
```

### User Management
- **GET** `/admin/users?page=1&limit=10`
- **GET** `/admin/recent-users?limit=10`
- **PUT** `/admin/users/status`

### Campaign Management
- **GET** `/admin/campaigns?status=active`
- **PUT** `/admin/campaigns/status`
- **POST** `/admin/campaign/create`

### Transaction Management
- **GET** `/admin/transactions?page=1&limit=20&status=completed`
- **PUT** `/admin/transactions/status`

### Reports Management
- **GET** `/admin/reports?status=pending`
- **PUT** `/admin/reports/action`

### System Settings
- **GET** `/admin/settings`
- **PUT** `/admin/settings`

---

## 📱 Frontend Pages Connected

### User Pages
✅ **User Dashboard** (`/pages/userDashboard.html`)
- Real-time wallet balance
- Campaign listings
- Recent activity
- User profile integration

✅ **Campaign Upload** (`/pages/campaign_upload.html`)
- Campaign creation form
- File upload handling
- Budget management

✅ **Campaign Progress** (`/pages/campaignProgress.html`)
- Real-time campaign metrics
- Participant tracking
- Performance analytics

✅ **Task Engagement** (`/pages/task_engage.html`)
- Available tasks listing
- Task completion tracking
- Reward claiming

✅ **Wallet** (`/pages/Wallet.html`)
- Balance display
- Transaction history
- Buy/Convert/Withdraw functions
- On-chain integration

✅ **Settings** (`/pages/settings.html`)
- Profile management
- Notification preferences
- Privacy controls

### Admin Pages
✅ **Admin Dashboard** (`/pages/admin_dashboard/dashboard1.html`)
- Real-time user statistics
- Recent registrations table
- System overview

✅ **User Management** (`/pages/admin_dashboard/user.html`)
- User listing and search
- Status management
- User details

✅ **Campaign Management** (`/pages/admin_dashboard/campaigns.html`)
- Campaign oversight
- Status controls
- Performance metrics

✅ **Transaction Management** (`/pages/admin_dashboard/transactions.html`)
- Transaction monitoring
- Status updates
- Financial reporting

✅ **Reports** (`/pages/admin_dashboard/reports.html`)
- User reports handling
- Moderation tools
- Action tracking

✅ **Settings** (`/pages/admin_dashboard/settings.html`)
- System configuration
- Platform settings
- Administrative controls

---

## 🔧 Technical Implementation

### Database Collections
- `users` - User profiles and authentication
- `campaigns` - Campaign data and metadata
- `tasks` - Task definitions and tracking
- `transactions` - Financial transactions
- `settings` - User and system settings
- `reports` - User reports and moderation

### Authentication Flow
1. **Email/Password**: Register → OTP Verification → JWT Token
2. **Twitter OAuth**: Redirect → Callback → User Creation → JWT Token
3. **Google OAuth**: Code Exchange → User Creation → JWT Token

### Security Features
- JWT token authentication
- Password hashing
- OTP verification
- Session management
- CORS protection
- Input validation

---

## 🚀 Getting Started

### 1. Start the Server
```bash
cd /path/to/partivox
php -S localhost:8000
```

### 2. Access the Application
- **User Interface**: `http://localhost:8000/pages/userDashboard.html`
- **Admin Interface**: `http://localhost:8000/pages/admin_dashboard/login.html`
- **API Base**: `http://localhost:8000/api/`

### 3. Test Authentication
1. Register a new user via `/pages/admin_dashboard/signup.html`
2. Verify OTP and get JWT token
3. Use token for authenticated API calls

---

## 📊 Response Format

All API responses follow this format:
```json
{
  "status": "success|error",
  "message": "Human readable message",
  "data": {}, // Response data (success only)
  "error": "Error details" // Error only
}
```

---

## 🎯 Status Codes
- `200` - Success
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `405` - Method Not Allowed
- `500` - Internal Server Error

---

**🎉 Your PARTIVOX application is now fully connected and ready for production!**
