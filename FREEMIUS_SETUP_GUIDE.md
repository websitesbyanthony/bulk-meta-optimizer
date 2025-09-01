# Freemius Integration Setup Guide

## Overview
Your plugin has been successfully migrated from the custom SLM (Software License Manager) system to Freemius. This guide will help you complete the setup.

## What Was Changed

### ✅ Removed (SLM System)
- All `BMO_SLM_*` constants and configuration
- Custom license verification functions (`bmo_check_license_status`, `bmo_deactivate_license`, etc.)
- License key input fields and status displays in admin
- Manual license check AJAX functionality
- Daily license check scheduling
- Custom license activation/deactivation hooks

### ✅ Added (Freemius System)
- Freemius SDK integration with `bmo_fs()` helper function
- Updated usage limit checks using `bmo_fs()->is_paying()`
- Freemius upgrade URLs for free users
- Free user usage tracking (10 optimizations limit)
- Bulk operation limits for free users (5 posts max)

## Required Setup Steps

### 1. Download Freemius SDK
```bash
cd /path/to/your/plugin
wget https://github.com/Freemius/wordpress-sdk/archive/refs/heads/master.zip
unzip master.zip
mv wordpress-sdk-master/freemius ./freemius
rm -rf wordpress-sdk-master master.zip
```

### 2. Create Freemius Account & Plugin
1. Go to https://developers.freemius.com/
2. Create an account or log in
3. Click "Add Plugin"
4. Fill in your plugin details:
   - **Name**: Bulk Meta Optimizer
   - **Slug**: bulk-meta-optimizer
   - **Type**: Plugin
   - **Monetization**: Paid

### 3. Configure Plugin Settings
After creating your plugin in Freemius, you'll get:
- **Plugin ID** (e.g., 15999)
- **Public Key** (e.g., pk_abc123...)

Update these in `ai-content-optimizer.php` around line 36:
```php
$bmo_fs = fs_dynamic_init( array(
    'id'                  => 'YOUR_PLUGIN_ID_HERE',  // Replace with actual ID
    'slug'                => 'bulk-meta-optimizer',
    'type'                => 'plugin',
    'public_key'          => 'YOUR_PUBLIC_KEY_HERE', // Replace with actual key
    'is_premium'          => false,
    'is_premium_only'     => false,
    'has_addons'          => false,
    'has_paid_plans'      => true,
    'menu'                => array(
        'slug'           => 'ai-content-optimizer',
        'override_exact' => true,
        'first-path'     => 'admin.php?page=ai-content-optimizer',
        'support'        => false,
    ),
) );
```

### 4. Set Up Pricing Plans
In your Freemius dashboard:
1. Go to "Plans & Pricing"
2. Create your pricing plans (e.g., Pro Plan - $29/year)
3. Set features and limitations

### 5. Test Integration
1. Install the plugin on a test site
2. Verify Freemius menu appears
3. Test free user limitations (10 optimizations, 5 bulk posts)
4. Test upgrade flow
5. Test with a paid license

## New User Experience

### Free Users
- 10 total optimizations allowed
- Bulk operations limited to 5 posts
- Upgrade prompts with direct links to pricing

### Paid Users
- Unlimited optimizations
- Unlimited bulk operations
- No restrictions

## Migration Notes

### Existing Users
- Old license keys and status will be ignored
- Users will need to purchase through Freemius
- Consider offering migration discounts

### Database Cleanup
You may want to clean up old options:
```php
// Optional: Clean up old SLM options
delete_option('bmo_license_key');
delete_option('bmo_license_status');
delete_option('aico_unlicensed_usage_count');
delete_transient('bmo_license_last_check');
```

## Freemius Features You Now Have

### Automatic Updates
- Paid users get automatic updates
- No need to manage update servers

### Analytics
- User engagement tracking
- Revenue analytics
- Conversion metrics

### Support System
- Built-in contact forms
- User management
- License management

### Payment Processing
- Handles all payment processing
- Multiple payment methods
- Automatic renewals

## Troubleshooting

### Common Issues
1. **SDK not found**: Ensure `freemius/start.php` exists
2. **Invalid plugin ID**: Check your Freemius dashboard for correct ID
3. **Menu conflicts**: Adjust menu settings in fs_dynamic_init()

### Testing Checklist
- [ ] Plugin activates without errors
- [ ] Freemius menu appears in admin
- [ ] Free user limits work correctly
- [ ] Upgrade URLs work
- [ ] Paid license removes restrictions
- [ ] Bulk operations respect limits

## Support
- Freemius Documentation: https://freemius.com/help/
- Freemius SDK GitHub: https://github.com/Freemius/wordpress-sdk
- WordPress Plugin Guidelines: https://developer.wordpress.org/plugins/
