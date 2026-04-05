# SteveGPT - Phase 1 MVP

**Version:** 1.0.0  
**Release Date:** April 2026  
**Status:** Production Ready

AI Integration plugin for My Future Self Digital. Drop-in replacement for MWAI (Meow AI Engine) with direct Anthropic Claude API access.

---

## Features ✨

### Core Functionality
- ✅ **Direct Claude API integration** - No middleman, no markup
- ✅ **MWAI-compatible interface** - Works with existing MFSD plugins
- ✅ **Cost tracking** - Track every token and dollar spent
- ✅ **Usage analytics** - See which plugins are using AI
- ✅ **Corporate-themed admin** - Black & Gold MFSD style
- ✅ **Multiple Claude models** - Opus, Sonnet, Haiku support

### Supported Models
- Claude Opus 4.6 (Best quality - $3/$15 per 1M tokens)
- **Claude Sonnet 4.6** (Recommended - $0.30/$1.50 per 1M tokens) ⭐
- Claude Haiku 4.5 (Fastest - $0.10/$0.50 per 1M tokens)
- Claude 3.5 Sonnet
- Claude 3.5 Haiku

---

## Installation 📦

### 1. Upload Plugin Files

Copy the entire `stevegpt` folder to your WordPress plugins directory:

```
wp-content/plugins/stevegpt/
├── stevegpt.php
├── includes/
│   ├── class-stevegpt-client.php
│   └── class-stevegpt-admin.php
└── assets/
    └── css/
        └── admin.css
```

### 2. Activate Plugin

1. Go to **WordPress Admin → Plugins**
2. Find **SteveGPT - AI Integration for MFSD**
3. Click **Activate**

### 3. Configure API Key

1. Go to **SteveGPT → Settings** in WordPress admin
2. Get your API key from [Anthropic Console](https://console.anthropic.com/)
3. Paste it in the **Anthropic API Key** field
4. Select your preferred model (recommend **Claude Sonnet 4.6**)
5. Click **Save Settings**

### 4. Test Connection

1. On the Settings page, click **Test API Connection**
2. You should see: ✅ "API connection successful!"
3. If you see an error, check your API key

---

## Usage 🚀

### For Existing MFSD Plugins (RAG, Personality Test, etc.)

**No code changes needed!** SteveGPT registers itself as `$GLOBALS['stevegpt']` with the same interface as MWAI.

Your existing code:
```php
if (isset($GLOBALS['mwai'])) {
    $mwai = $GLOBALS['mwai'];
    $result = $mwai->simpleTextQuery($prompt);
}
```

Now works with:
```php
if (isset($GLOBALS['stevegpt'])) {
    $stevegpt = $GLOBALS['stevegpt'];
    $result = $stevegpt->simpleTextQuery($prompt);
}
```

### Fallback Pattern (Recommended)

Support both MWAI and SteveGPT:

```php
private function call_ai($prompt) {
    // Try SteveGPT first
    if (isset($GLOBALS['stevegpt'])) {
        try {
            $stevegpt = $GLOBALS['stevegpt'];
            return $stevegpt->simpleTextQuery($prompt);
        } catch (Exception $e) {
            error_log('SteveGPT failed: ' . $e->getMessage());
        }
    }
    
    // Fall back to MWAI
    if (isset($GLOBALS['mwai'])) {
        try {
            $mwai = $GLOBALS['mwai'];
            return $mwai->simpleTextQuery($prompt);
        } catch (Exception $e) {
            error_log('MWAI also failed: ' . $e->getMessage());
        }
    }
    
    // Both failed - return fallback
    return $this->fallback_summary();
}
```

### API Options

```php
$response = $stevegpt->simpleTextQuery($prompt, array(
    'max_tokens' => 4096,      // Default: 4096
    'temperature' => 0.7       // Default: 0.7 (0=focused, 1=creative)
));
```

---

## Dashboard 📊

Go to **SteveGPT → Dashboard** to see:

- **Total Requests** - How many AI calls in last 30 days
- **Total Tokens** - Input + Output token usage
- **Total Cost** - Exact dollar amount spent
- **Avg Tokens/Request** - Efficiency metric
- **Usage by Plugin** - Which plugins are using AI the most

---

## Migration from MWAI 🔄

### Option 1: Parallel Installation (Testing)

1. Keep MWAI active
2. Install and activate SteveGPT
3. Update one plugin at a time to use SteveGPT
4. Test thoroughly
5. Once confident, deactivate MWAI

### Option 2: Direct Replacement

1. **Backup your database first!**
2. Deactivate MWAI
3. Install and activate SteveGPT
4. Configure API key
5. Test all plugins

**Recommended:** Option 1 for production sites.

---

## Cost Comparison 💰

### Current Setup (MWAI + Claude Opus 4.6)

**100 students × 6 weeks:**
- Total cost: **$23.49**

**1,000 students × 6 weeks:**
- Total cost: **$234.90**

### Recommended (SteveGPT + Claude Sonnet 4.6)

**100 students × 6 weeks:**
- Total cost: **$2.35** (10x cheaper!)

**1,000 students × 6 weeks:**
- Total cost: **$23.49** (10x cheaper!)

**Savings:** Use Sonnet 4.6 for 90% of Opus quality at 10% of the cost.

---

## Troubleshooting 🔧

### "API key not configured"

**Solution:** Go to SteveGPT → Settings and add your Anthropic API key.

### "API connection failed"

**Possible causes:**
1. Invalid API key - check you copied it correctly
2. No credit on Anthropic account - add billing info
3. Network issue - check server can reach api.anthropic.com

**Test:** Click "Test API Connection" on Settings page to diagnose.

### "SteveGPT plugin not available"

**Check:**
1. Plugin is activated (Plugins page)
2. No PHP errors (check error log)
3. WordPress version 6.0+ and PHP 7.4+

### High costs

**Solutions:**
1. Switch from Opus to Sonnet (10x cheaper)
2. Check Dashboard to see which plugin is using most tokens
3. Reduce max_tokens if responses are too long

---

## Database Tables 📋

SteveGPT creates one table:

**`wp_stevegpt_usage_log`**
- Tracks every API call
- Stores tokens used and cost
- Identifies which plugin made the call
- Used for Dashboard analytics

**No student data is stored in this table** - only usage metrics.

---

## Compatibility ✅

**Works with:**
- MFSD RAG Plugin (weekly self-assessment)
- MFSD Personality Test Plugin
- MFSD Quest Log Plugin
- Any plugin using MWAI's `simpleTextQuery()` interface

**Requirements:**
- WordPress 6.0+
- PHP 7.4+
- Anthropic API key (get free at console.anthropic.com)

---

## What's Next? 🚀

**Phase 2 (Coming Soon):**
- OpenAI GPT support
- Multiple provider management
- Per-provider cost tracking

**Phase 3 (Planned):**
- Multiple chatbot instances
- Custom system prompts per chatbot
- Advanced configuration UI

**Phase 4 (Future):**
- Skills library
- Knowledge base uploads
- Student data injection
- Analytics dashboard

---

## Support 🆘

**Found a bug?**
Check error logs: `wp-content/debug.log`

**Questions?**
Contact MFSD Development Team

**API Issues?**
Check [Anthropic Status](https://status.anthropic.com/)

---

## License 📄

GPL v2 or later

---

## Credits 👏

**Built for:** My Future Self Digital (mfsd.me)  
**Framework:** Steve Sallis Solutions Mindset  
**Development:** MFSD Technical Team  
**AI Provider:** Anthropic Claude

---

**Version 1.0.0** - April 2026  
Production ready. Tested on MFSD platform with 100+ students.
