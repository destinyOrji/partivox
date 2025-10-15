# MongoDB Atlas Setup for PartiVox Production

## 🗄️ Step 1: Create MongoDB Atlas Account
1. Go to https://www.mongodb.com/atlas
2. Sign up for free account
3. Create a new cluster (free tier: M0 Sandbox)
4. Get your connection string

## 🔧 Step 2: Update Environment Variables
In your Render dashboard, add:
```
DB_HOST=mongodb+srv://username:password@cluster.mongodb.net
DB_PORT=27017
DB_NAME=partivox_production
```

## 📝 Step 3: Update Database Configuration
Replace the file-based storage with MongoDB in `api/config/db.php`:

```php
// Remove the Render detection and use MongoDB directly
private function __construct() {
    try {
        $connectionString = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
        $dbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'partivox';
        
        // Create MongoDB client
        $this->client = new MongoDB\Client($connectionString);
        $this->db = $this->client->$dbName;
        
        // Initialize collections
        $this->initializeCollections();
        $this->createIndexes();
        
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}
```

## 🚀 Step 4: Deploy
1. Update environment variables in Render
2. Push changes to GitHub
3. Render will automatically redeploy

## 💰 Cost Comparison
- **File-based (current)**: Free ✅
- **MongoDB Atlas**: Free tier (512MB) ✅
- **Render**: Free tier ✅

## 🎯 When to Switch
- ✅ When you have real users
- ✅ When you need persistent data
- ✅ When you're ready for production
- ❌ For testing/demo (current setup is fine)
