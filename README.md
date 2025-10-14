# 🎯 PARTIVOX - Complete Social Media Campaign Platform

![PARTIVOX Logo](images/IconDiamond.png)

## 🌟 Overview

PARTIVOX is a comprehensive social media campaign management platform that enables users to create, manage, and participate in social media campaigns while earning rewards through a diamond-based economy.

## ✨ Features

### 🔐 Multi-Authentication System
- **Email/Password** registration with OTP verification
- **Twitter OAuth** integration
- **Google OAuth** support
- **JWT-based** secure authentication
- **Real-time** user session management

### 💎 Diamond Economy & Wallet
- **Digital wallet** with diamond and USDT balance
- **Buy diamonds** with cryptocurrency
- **Convert diamonds** to USDT
- **Withdraw USDT** to external wallets
- **On-chain transaction** confirmation
- **Complete transaction** history

### 🎯 Campaign Management
- **Create campaigns** with custom parameters
- **Upload media** and set target audiences
- **Track campaign** progress in real-time
- **Manage participants** and engagement
- **Set rewards** and budgets
- **Campaign analytics** and reporting

### ✅ Task Engagement System
- **Browse available** tasks from campaigns
- **Complete tasks** and submit proof
- **Earn rewards** automatically
- **Track task** progress and history
- **Real-time** reward distribution

### ⚙️ User Settings & Profiles
- **Profile management** with bio and avatar
- **Notification preferences** control
- **Privacy settings** configuration
- **Account security** options
- **Social media** account linking

### 🛡️ Admin Dashboard
- **User management** with real-time statistics
- **Campaign oversight** and moderation
- **Transaction monitoring** and control
- **Reports handling** and user moderation
- **System settings** and configuration
- **Analytics and** performance metrics

## 🏗️ Technical Architecture

### Backend (PHP 8.0+)
```
/api/
├── config/          # Database and service configurations
├── controllers/     # Business logic controllers
├── middleware/      # Authentication and validation
├── models/         # Data models and database interactions
├── routes/         # API endpoint definitions
└── index.php       # Main API entry point
```

### Frontend (HTML5/CSS3/JavaScript)
```
/pages/
├── admin_dashboard/    # Admin interface pages
├── css/               # Stylesheets
├── javascript/        # Client-side scripts
├── userDashboard.html # Main user interface
├── campaign_upload.html # Campaign creation
├── task_engage.html   # Task management
├── Wallet.html        # Wallet interface
└── settings.html      # User settings
```

### Database (MongoDB)
- **users** - User profiles and authentication data
- **campaigns** - Campaign information and metadata
- **tasks** - Task definitions and completion tracking
- **transactions** - Financial transaction records
- **settings** - User and system configuration
- **reports** - User reports and moderation data

## 🚀 Quick Start

### 1. Prerequisites
- PHP 8.0+ with MongoDB extension
- MongoDB 4.4+
- Composer for dependency management
- Web server (Apache/Nginx) or PHP built-in server

### 2. Installation
```bash
# Clone the repository
git clone https://github.com/yourusername/partivox.git
cd partivox

# Install PHP dependencies
composer install

# Configure environment
cp .env.example .env
# Edit .env with your configuration

# Start the development server
php -S localhost:8000
```

### 3. Access the Application
- **User Interface**: http://localhost:8000/pages/userDashboard.html
- **Admin Interface**: http://localhost:8000/pages/admin_dashboard/login.html
- **API Documentation**: See API_DOCUMENTATION.md

## 📱 User Interface

### User Dashboard
![User Dashboard](images/dashboard-preview.png)
- Real-time wallet balance display
- Active campaigns overview
- Recent activity feed
- Quick access to all features

### Campaign Management
- Intuitive campaign creation wizard
- Media upload and management
- Target audience selection
- Budget and reward configuration
- Real-time progress tracking

### Task Engagement
- Browse available tasks by category
- Step-by-step task instructions
- Proof submission interface
- Automatic reward distribution
- Progress tracking and history

### Wallet Interface
- Diamond and USDT balance display
- Buy diamonds with crypto
- Convert diamonds to USDT
- Withdraw to external wallets
- Complete transaction history
- On-chain integration support

## 🛡️ Admin Features

### Real-Time Dashboard
- Live user registration tracking
- Campaign performance metrics
- Financial transaction monitoring
- System health indicators
- User engagement analytics

### User Management
- Comprehensive user database
- Account status management
- Verification controls
- Activity monitoring
- Bulk operations support

### Campaign Oversight
- Campaign approval workflow
- Performance monitoring
- Budget tracking
- Participant management
- Content moderation

### Financial Controls
- Transaction monitoring
- Revenue tracking
- Withdrawal management
- Fraud detection
- Financial reporting

## 🔧 API Endpoints

### Authentication
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - Email login
- `POST /api/auth/verify-otp` - OTP verification
- `GET /api/twitter/twitter-auth.php` - Twitter OAuth
- `POST /api/auth/google` - Google OAuth

### Wallet & Transactions
- `GET /api/wallet/balance` - Get wallet balance
- `POST /api/wallet/buy` - Buy diamonds
- `POST /api/wallet/convert` - Convert to USDT
- `POST /api/wallet/withdraw` - Withdraw USDT
- `GET /api/wallet/transactions` - Transaction history

### Campaign Management
- `POST /api/campaigns/upload` - Create campaign
- `GET /api/campaigns/list` - List campaigns
- `GET /api/campaigns/view` - Campaign details
- `GET /api/campaigns/progress` - Campaign progress

### Task Management
- `GET /api/tasks/list` - Available tasks
- `PUT /api/tasks/status` - Update task status

### Admin Operations
- `GET /api/admin/dashboard` - Dashboard statistics
- `GET /api/admin/users` - User management
- `GET /api/admin/campaigns` - Campaign oversight
- `GET /api/admin/transactions` - Transaction monitoring

*For complete API documentation, see [API_DOCUMENTATION.md](API_DOCUMENTATION.md)*

## 🔒 Security Features

### Authentication & Authorization
- JWT token-based authentication
- Secure password hashing (bcrypt)
- OTP verification for email accounts
- OAuth integration for social logins
- Session management and timeout

### Data Protection
- Input validation and sanitization
- SQL injection prevention
- XSS protection
- CSRF token validation
- Secure file upload handling

### Privacy Controls
- User data encryption
- Privacy settings management
- GDPR compliance features
- Data retention policies
- Secure data deletion

## 🌐 Deployment

### Development
```bash
# Start development server
php -S localhost:8000

# Access application
open http://localhost:8000/pages/userDashboard.html
```

### Production
For production deployment, see [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for:
- Server configuration
- Database setup
- Security hardening
- Performance optimization
- Monitoring setup

## 📊 Database Schema

### Users Collection
```javascript
{
  _id: ObjectId,
  name: String,
  email: String,
  password: String, // hashed
  twitter_id: String,
  twitter_handle: String,
  auth_provider: String, // 'email', 'twitter', 'google'
  is_verified: Boolean,
  role: String, // 'user', 'admin'
  created_at: Date,
  updated_at: Date
}
```

### Campaigns Collection
```javascript
{
  _id: ObjectId,
  user_id: ObjectId,
  title: String,
  description: String,
  budget: Number,
  status: String, // 'draft', 'active', 'completed', 'suspended'
  start_date: Date,
  end_date: Date,
  participants: Array,
  created_at: Date
}
```

### Transactions Collection
```javascript
{
  _id: ObjectId,
  user_id: ObjectId,
  type: String, // 'buy_diamonds', 'convert_to_usdt', 'withdraw_usdt'
  amount: Number,
  status: String, // 'pending', 'completed', 'failed'
  tx_hash: String, // for blockchain transactions
  created_at: Date
}
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

### Documentation
- [API Documentation](API_DOCUMENTATION.md)
- [Deployment Guide](DEPLOYMENT_GUIDE.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)

### Contact
- **Email**: support@partivox.com
- **Discord**: [PARTIVOX Community](https://discord.gg/partivox)
- **Twitter**: [@PartivoxApp](https://twitter.com/PartivoxApp)

## 🎯 Roadmap

### Phase 1 ✅ (Completed)
- [x] Multi-authentication system
- [x] User dashboard and profile management
- [x] Campaign creation and management
- [x] Task engagement system
- [x] Digital wallet with diamond economy
- [x] Admin dashboard with real-time analytics
- [x] Complete API backend
- [x] Responsive frontend interface

### Phase 2 🚧 (In Progress)
- [ ] Mobile app development (React Native)
- [ ] Advanced analytics and reporting
- [ ] Multi-language support
- [ ] Enhanced security features
- [ ] API rate limiting and caching

### Phase 3 🔮 (Planned)
- [ ] AI-powered campaign optimization
- [ ] Advanced blockchain integration
- [ ] Third-party integrations (Instagram, TikTok)
- [ ] White-label solutions
- [ ] Enterprise features

---

**🎉 PARTIVOX - Empowering Social Media Campaigns with Blockchain Technology**

*Built with ❤️ by the PARTIVOX Team*
