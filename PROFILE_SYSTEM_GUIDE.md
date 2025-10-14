# Profile Image System Guide

This guide explains how the profile image system works across all pages in the Partivox application.

## Overview

The profile image system provides consistent user avatar display across all pages, with the ability to upload and update profile pictures through the settings page.

## Architecture

### Backend Components

1. **API Endpoints**
   - `GET /api/settings/profile-picture` - Get user's current profile picture
   - `POST /api/settings/upload-profile-picture` - Upload new profile picture
   - `GET /api/settings/profile` - Get user profile information

2. **Storage**
   - Profile pictures stored in `/uploads/profile_pictures/`
   - Database stores the relative path to the image
   - Filenames include user ID and timestamp for uniqueness

3. **Validation**
   - File type validation (JPG, PNG, GIF, WebP)
   - File size limit (5MB)
   - Multiple validation methods for cross-platform compatibility

### Frontend Components

1. **Profile Utilities (`javascript/profileUtils.js`)**
   - Shared functions for loading avatars and profiles
   - Consistent API calls across all pages
   - Event system for real-time updates

2. **Page-Specific Integration**
   - Each page includes avatar loading functionality
   - Consistent HTML structure for avatar containers
   - Fallback to default avatars when no custom image

## Usage

### Basic Implementation

```html
<!-- HTML Structure -->
<div class="user-avatar" id="userAvatar">
  <img src="../images/avartar1.jpg" alt="Default Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
</div>
```

```javascript
// JavaScript Implementation
async function loadUserAvatar() {
  try {
    const token = await getValidToken();
    if (!token) return;

    const response = await fetch(`${API_BASE_URL}api/settings/profile-picture`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    if (response.ok) {
      const data = await response.json();
      if (data.status === 'success' && data.data.profile_picture_url) {
        const userAvatar = document.getElementById('userAvatar');
        if (userAvatar) {
          userAvatar.innerHTML = `<img src="${data.data.profile_picture_url}?t=${Date.now()}" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`;
        }
      }
    }
  } catch (error) {
    console.error('Error loading user avatar:', error);
  }
}
```

### Using Profile Utilities (Recommended)

```html
<!-- Include the utility script -->
<script src="../javascript/profileUtils.js"></script>
```

```javascript
// Simple initialization
document.addEventListener('DOMContentLoaded', function() {
  // Initialize all profile components
  ProfileUtils.initializeProfile({
    avatarElementId: 'userAvatar',
    profileElements: {
      handle: 'userHandle',
      name: 'userName'
    },
    walletElementId: 'walletAddress'
  });
});

// Or load components individually
ProfileUtils.loadUserAvatar('userAvatar');
ProfileUtils.loadUserProfile({ handle: 'userHandle' });
ProfileUtils.updateWalletAddress('walletAddress');
```

## Pages Implementation Status

### ✅ Completed Pages

1. **userDashboard.html**
   - Avatar loading integrated
   - Uses ProfileUtils for consistency
   - Loads on page initialization

2. **Wallet.html**
   - Avatar loading function updated
   - Proper API endpoint usage
   - Integrated with existing functionality

3. **campaign_upload.html**
   - New avatar loading function added
   - Integrated with page initialization
   - Consistent styling

4. **campaignProgress.html**
   - Avatar loading with Promise.all
   - Parallel loading with profile data
   - Proper error handling

5. **task_engage.html**
   - Avatar loading on initialization
   - Consistent with other pages
   - Proper fallback handling

6. **settings.html**
   - Full avatar upload functionality
   - Source of truth for profile pictures
   - Real-time preview and updates

## API Endpoints

### Get Profile Picture

```http
GET /api/settings/profile-picture
Authorization: Bearer <jwt_token>
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "profile_picture_url": "/uploads/profile_pictures/user_123_1640995200.jpg"
  }
}
```

### Upload Profile Picture

```http
POST /api/settings/upload-profile-picture
Authorization: Bearer <jwt_token>
Content-Type: multipart/form-data

profile_picture: <file>
```

**Response:**
```json
{
  "status": "success",
  "message": "Profile picture uploaded successfully",
  "data": {
    "profile_picture_url": "/uploads/profile_pictures/user_123_1640995200.jpg"
  }
}
```

## File Structure

```
partivox/
├── api/
│   ├── controllers/
│   │   └── SettingsController.php     # Profile picture handling
│   └── routes/
│       └── settings.route.php         # Profile picture endpoints
├── javascript/
│   └── profileUtils.js                # Shared profile utilities
├── uploads/
│   └── profile_pictures/              # Uploaded profile pictures
└── pages/
    ├── userDashboard.html            # ✅ Avatar loading
    ├── Wallet.html                   # ✅ Avatar loading
    ├── campaign_upload.html          # ✅ Avatar loading
    ├── campaignProgress.html         # ✅ Avatar loading
    ├── task_engage.html              # ✅ Avatar loading
    └── settings.html                 # ✅ Avatar upload & display
```

## Features

### 🎯 Core Features

- **Consistent Display**: Same avatar appears on all pages
- **Real-time Updates**: Changes propagate immediately
- **Fallback System**: Default avatars when no custom image
- **Security**: Proper file validation and authentication
- **Performance**: Cache-busting and optimized loading

### 🔧 Technical Features

- **Multiple Validation Methods**: Works across different PHP configurations
- **Error Handling**: Graceful degradation on failures
- **Event System**: Custom events for component communication
- **Responsive Design**: Proper sizing for different layouts
- **Cross-Authentication**: Works with JWT and session auth

### 🎨 UI Features

- **Professional Styling**: Consistent border-radius and sizing
- **Hover Effects**: Full address display on wallet hover
- **Loading States**: Proper feedback during uploads
- **Preview System**: Real-time preview in settings

## Best Practices

### For Developers

1. **Always use the ProfileUtils** for new pages
2. **Include proper error handling** for API calls
3. **Use cache-busting** with timestamp parameters
4. **Maintain consistent HTML structure** for avatars
5. **Test with and without custom avatars**

### For Users

1. **Upload high-quality images** for best results
2. **Use square images** for proper display
3. **Keep file sizes reasonable** (under 5MB)
4. **Use supported formats** (JPG, PNG, GIF, WebP)

## Troubleshooting

### Common Issues

1. **Avatar not loading**
   - Check authentication token
   - Verify API endpoint is accessible
   - Check browser console for errors

2. **Upload failing**
   - Verify file size (max 5MB)
   - Check file format (JPG, PNG, GIF, WebP)
   - Ensure proper authentication

3. **Inconsistent display**
   - Clear browser cache
   - Check if all pages use same API endpoint
   - Verify HTML structure consistency

### Debug Steps

1. **Check browser console** for error messages
2. **Verify API responses** in Network tab
3. **Test with different file types** and sizes
4. **Check server logs** for backend errors
5. **Validate authentication** tokens

## Future Enhancements

### Planned Features

- **Image cropping** in upload modal
- **Multiple image sizes** for different contexts
- **CDN integration** for better performance
- **Image optimization** on upload
- **Bulk avatar management** for admins

### Technical Improvements

- **WebP conversion** for better compression
- **Progressive loading** for large images
- **Offline caching** for better performance
- **Image validation** on client-side
- **Drag-and-drop upload** interface

## Conclusion

The profile image system provides a robust, consistent way to handle user avatars across the Partivox application. With proper validation, error handling, and a clean API, users can easily personalize their profiles while maintaining a professional appearance throughout the platform.

For questions or issues, refer to the troubleshooting section or check the implementation in `settings.html` for the complete upload functionality.
