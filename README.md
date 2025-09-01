# Bulk Meta Optimizer - Brand Profile Integration

## Overview
The Bulk Meta Optimizer plugin now includes a comprehensive "Brand Profile" feature that integrates with all AI content generation. The brand profile is automatically used in all prompts sent to OpenAI, ensuring consistent brand voice and messaging across all optimized content.

## Brand Profile Features

### 1. Build My Profile
- **AI-Powered Analysis**: The system analyzes your website's homepage content using OpenAI's AI models
- **Automatic Profile Generation**: Creates a comprehensive brand profile including:
  - Brand Overview
  - Target Audience
  - Brand Tone
  - Unique Selling Points
- **Smart Content Extraction**: Automatically finds and analyzes your homepage content
- **Site Information Integration**: Includes your website title and tagline in the analysis

### 2. Edit Profile
- **Editable Sections**: All generated content is presented in editable text blocks
- **User-Friendly Interface**: Clean, organized layout with clear section headers
- **Real-time Saving**: Save changes instantly with AJAX-powered updates
- **Rebuild Option**: Regenerate the entire profile if needed

### 3. Automatic Integration
- **All Prompts**: Brand profile data is automatically integrated into all AI prompts
- **Consistent Branding**: Ensures all generated content follows your brand guidelines
- **Dynamic Variables**: Uses brand profile data in title, meta description, and content prompts

## Content Style Options Removal

### What Changed
- **Removed**: Content Style Options section from all settings pages
- **Replaced**: Old style variables with brand profile variables
- **Simplified**: Settings interface now focuses on core optimization options

### New Prompt Variables
The following variables are now available in all AI prompts:
- `{brand_overview}` - Brand overview from brand profile
- `{target_audience}` - Target audience from brand profile  
- `{brand_tone}` - Brand tone from brand profile
- `{unique_selling_points}` - Unique selling points from brand profile
- `{site_title}` - Website title from WordPress settings
- `{site_tagline}` - Website tagline from WordPress settings

### Removed Variables
The following old variables are no longer available:
- `{content_tone}` - Replaced by `{brand_tone}`
- `{content_focus}` - Removed
- `{seo_aggressiveness}` - Removed
- `{keyword_density}` - Removed
- `{geographic_targeting}` - Removed
- `{brand_voice}` - Replaced by `{brand_tone}`

## Bulk Optimize Feature

### How to Use Bulk Optimization
1. **Select Items**: Go to any post, page, or custom post type list (e.g., Posts, Pages, Products)
2. **Choose Items**: Select multiple items using the checkboxes
3. **Bulk Action**: From the "Bulk Actions" dropdown, select "Optimize with AI"
4. **Apply**: Click "Apply" to start the bulk optimization process
5. **Progress Bar**: A dedicated page will show the progress with a real-time progress bar
6. **Results**: View detailed results including success count and any errors

### Bulk Optimization Features
- **Real-time Progress**: Live progress bar showing current item being processed
- **Error Handling**: Detailed error reporting for failed optimizations
- **Retry Functionality**: Option to retry failed items
- **Results Summary**: Clear summary of successful and failed optimizations
- **License Verification**: Ensures valid license before processing
- **Permission Checks**: Verifies user permissions for bulk operations
- **Brand Profile Integration**: All bulk optimizations use your brand profile data

## How to Use

### Step 1: Access Brand Profile
1. Go to WordPress Admin → Bulk Meta Optimizer → Brand Profile
2. Ensure you have a valid license and OpenAI API key configured

### Step 2: Build Your Profile
1. Click the "Build My Profile" button
2. The system will analyze your homepage content
3. Wait for the AI to generate your brand profile
4. The page will automatically refresh to show the edit form

### Step 3: Edit and Save
1. Review the generated content in each section
2. Edit any sections to better reflect your brand
3. Click "Save Profile" to store your changes
4. Use "Rebuild Profile" if you want to regenerate everything

### Step 4: Optimize Content
1. All content optimization (individual and bulk) now automatically uses your brand profile
2. No additional configuration needed - brand profile is integrated seamlessly
3. Customize prompts in Content Settings if needed, using the new brand profile variables

## Requirements
- OpenAI API key configured in plugin settings
- Homepage content available for analysis
- For unlimited features: Freemius license (free version has usage limits)

## Technical Details
- Uses OpenAI GPT models for content analysis
- Stores profile data in WordPress options table
- Implements AJAX for smooth user experience
- Includes fallback parsing for non-JSON AI responses
- Responsive design that works on all devices
- Bulk operations use WordPress transients for data storage
- Progress tracking with individual item processing
- Brand profile data automatically integrated into all AI prompts
- Simplified settings interface without content style options
- **NEW**: Freemius integration for licensing and payments

## Licensing & Usage Limits

### Free Version
- 10 total content optimizations
- Bulk optimization limited to 5 posts per operation
- Full access to brand profile features
- All core functionality available

### Premium Version
- Unlimited content optimizations
- Unlimited bulk operations
- Priority support
- Automatic updates

## Migration from SLM to Freemius
This version has migrated from a custom Software License Manager (SLM) system to Freemius. See `FREEMIUS_SETUP_GUIDE.md` for complete setup instructions.

## File Structure
```
bulk-meta-optimizer/
├── ai-content-optimizer.php (main plugin file with brand profile integration and bulk optimize functionality)
├── assets/
│   ├── css/
│   │   └── admin.css (brand profile and bulk progress styles)
│   └── js/
│       └── admin.js (brand profile and bulk optimize JavaScript)
└── README.md (this file)
```

## Support
For support with the Brand Profile feature, please contact the plugin developer or check the main plugin documentation. 