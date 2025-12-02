# Quick Start Guide - Hotel Hub Spoilt Linen Count

## Installation (5 minutes)

1. **Install the plugin:**
   ```bash
   cd /path/to/wordpress/wp-content/plugins/
   git clone https://github.com/jtricerolph/hotelhub-housekeeping-linencount.git
   ```

2. **Activate in WordPress:**
   - Go to **Plugins** in WordPress admin
   - Find **Hotel Hub Module - Housekeeping - Spoilt Linen Count**
   - Click **Activate**

## Configuration (10 minutes)

### Step 1: Enable the Module

1. Navigate to **Hotel Hub → Settings**
2. Select your location
3. Find **Linen Count Configuration**
4. Toggle **ON** to enable

### Step 2: Add Linen Items

Common linen items to add:

| Name | Shortcode | Size | Pack Qty | Target Stock |
|------|-----------|------|----------|--------------|
| Pillow Case | PC | Standard | 10 | 500 |
| Bed Sheet | BS | Queen | 5 | 300 |
| Double Bed Duvet | DBD | King | 3 | 150 |
| King Sheet | KSH | King | 5 | 200 |
| Bath Towel | BT | Large | 12 | 600 |
| Hand Towel | HT | Small | 15 | 400 |

**To add an item:**
1. Click **Add Linen Item**
2. Fill in the details
3. Click **Save Settings**

### Step 3: Set Permissions

**For Housekeeping Staff:**
- Enable: `hhlc_access_module`

**For Supervisors:**
- Enable: `hhlc_access_module`
- Enable: `hhlc_edit_submitted`

**For Managers:**
- Enable: `hhlc_access_module`
- Enable: `hhlc_edit_submitted`
- Enable: `hhlc_view_reports`

## Using the Module (2 minutes)

### Submit a Count

1. Open **Hotel Hub → Housekeeping → Daily List**
2. Click on any room
3. Scroll to **Spoilt Linen Count**
4. Tap **▲** to increase or **▼** to decrease counts
5. Click **Submit Count**

### View Reports

1. Go to **Hotel Hub → Reports → Linen Count Report**
2. Select location and month
3. Click any date to see detailed breakdown

### Export Data

1. In Reports, click **Export Data** tab
2. Select date range
3. Click **Download Report**

## Pushing to GitHub

```bash
cd c:/Users/JTR/Documents/GitHub/hotelhub-housekeeping-linencount
git push -u origin main
```

## Troubleshooting

### Module not showing up?

**Check:**
1. Hotel Hub App is activated
2. Housekeeping Daily List module is activated
3. Module is enabled in settings
4. User has required permissions

### Can't edit counts?

**Solution:**
- Only users with `hhlc_edit_submitted` permission can edit locked counts
- Check user permissions in **Hotel Hub → Workforce Authentication**

### Real-time sync not working?

**Check:**
1. WordPress Heartbeat is enabled
2. Check browser console for errors
3. Try refreshing the page

## Support

- **Issues**: [GitHub Issues](https://github.com/jtricerolph/hotelhub-housekeeping-linencount/issues)
- **Full Documentation**: [README.md](README.md)
