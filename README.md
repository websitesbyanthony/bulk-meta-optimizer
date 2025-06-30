# Bulk Meta Optimizer - Brand Profile Feature

## Overview
The Bulk Meta Optimizer plugin now includes a new "Brand Profile" feature that helps you create and manage a comprehensive brand profile for your website using AI.

## Brand Profile Features

### 1. Build My Profile
- **AI-Powered Analysis**: The system analyzes your website's homepage content using OpenAI's AI models
- **Automatic Profile Generation**: Creates a comprehensive brand profile including:
  - Brand Overview
  - Target Audience
  - Brand Tone
  - Unique Selling Points
- **Smart Content Extraction**: Automatically finds and analyzes your homepage content

### 2. Edit Profile
- **Editable Sections**: All generated content is presented in editable text blocks
- **User-Friendly Interface**: Clean, organized layout with clear section headers
- **Real-time Saving**: Save changes instantly with AJAX-powered updates
- **Rebuild Option**: Regenerate the entire profile if needed

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

## Requirements
- Valid Bulk Meta Optimizer license
- OpenAI API key configured in plugin settings
- Homepage content available for analysis

## Technical Details
- Uses OpenAI GPT models for content analysis
- Stores profile data in WordPress options table
- Implements AJAX for smooth user experience
- Includes fallback parsing for non-JSON AI responses
- Responsive design that works on all devices
- Bulk operations use WordPress transients for data storage
- Progress tracking with individual item processing

## File Structure
```
bulk-meta-optimizer/
├── ai-content-optimizer.php (main plugin file with brand profile and bulk optimize functionality)
├── assets/
│   ├── css/
│   │   └── admin.css (brand profile and bulk progress styles)
│   └── js/
│       └── admin.js (brand profile and bulk optimize JavaScript)
└── README.md (this file)
```

## Support
For support with the Brand Profile feature, please contact the plugin developer or check the main plugin documentation. 