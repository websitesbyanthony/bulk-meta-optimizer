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

## File Structure
```
bulk-meta-optimizer/
├── ai-content-optimizer.php (main plugin file with brand profile functionality)
├── assets/
│   ├── css/
│   │   └── admin.css (brand profile styles)
│   └── js/
│       └── admin.js (brand profile JavaScript)
└── README.md (this file)
```

## Support
For support with the Brand Profile feature, please contact the plugin developer or check the main plugin documentation. 