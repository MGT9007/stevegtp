<?php
/**
 * SteveGPT Admin Pages
 * Handles Dashboard and Settings pages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class SteveGPT_Admin {
    
    /**
     * Render dashboard page
     */
    public static function render_dashboard_page() {
        global $wpdb;
        
        // Get usage stats
        $stats = self::get_usage_stats();
        
        ?>
        <div class="wrap stevegpt-admin">
            <h1 class="stevegpt-page-title">
                <span class="stevegpt-icon">💬</span>
                SteveGPT Dashboard
            </h1>
            
            <p class="stevegpt-subtitle">AI Integration for My Future Self Digital</p>
            
            <div class="stevegpt-stats-grid">
                <div class="stevegpt-stat-card">
                    <div class="stevegpt-stat-icon">📊</div>
                    <div class="stevegpt-stat-value"><?php echo number_format($stats['total_requests']); ?></div>
                    <div class="stevegpt-stat-label">Total Requests</div>
                    <div class="stevegpt-stat-meta">Last 30 days</div>
                </div>
                
                <div class="stevegpt-stat-card">
                    <div class="stevegpt-stat-icon">🎯</div>
                    <div class="stevegpt-stat-value"><?php echo number_format($stats['total_tokens']); ?></div>
                    <div class="stevegpt-stat-label">Total Tokens</div>
                    <div class="stevegpt-stat-meta">Input + Output</div>
                </div>
                
                <div class="stevegpt-stat-card stevegpt-stat-card-gold">
                    <div class="stevegpt-stat-icon">💰</div>
                    <div class="stevegpt-stat-value">$<?php echo number_format($stats['total_cost'], 2); ?></div>
                    <div class="stevegpt-stat-label">Total Cost</div>
                    <div class="stevegpt-stat-meta">Direct API billing</div>
                </div>
                
                <div class="stevegpt-stat-card">
                    <div class="stevegpt-stat-icon">📈</div>
                    <div class="stevegpt-stat-value"><?php echo number_format($stats['avg_tokens']); ?></div>
                    <div class="stevegpt-stat-label">Avg Tokens/Request</div>
                    <div class="stevegpt-stat-meta">Efficiency metric</div>
                </div>
            </div>
            
            <div class="stevegpt-section">
                <h2>Usage by Plugin</h2>
                
                <?php if (empty($stats['by_plugin'])): ?>
                    <p style="color: #999;">No API calls yet. Start using SteveGPT to see statistics!</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped stevegpt-table">
                        <thead>
                            <tr>
                                <th>Plugin</th>
                                <th>Requests</th>
                                <th>Tokens</th>
                                <th>Cost</th>
                                <th>% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['by_plugin'] as $plugin): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($plugin->plugin_source); ?></strong></td>
                                    <td><?php echo number_format($plugin->requests); ?></td>
                                    <td><?php echo number_format($plugin->tokens); ?></td>
                                    <td>$<?php echo number_format($plugin->cost, 4); ?></td>
                                    <td><?php echo number_format(($plugin->cost / $stats['total_cost']) * 100, 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="stevegpt-section">
                <h2>Quick Links</h2>
                <div class="stevegpt-quick-links">
                    <a href="<?php echo admin_url('admin.php?page=stevegpt-settings'); ?>" class="stevegpt-quick-link">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <span>Settings</span>
                    </a>
                    <a href="https://console.anthropic.com" target="_blank" class="stevegpt-quick-link">
                        <span class="dashicons dashicons-external"></span>
                        <span>Anthropic Console</span>
                    </a>
                    <a href="https://docs.anthropic.com/claude/reference" target="_blank" class="stevegpt-quick-link">
                        <span class="dashicons dashicons-book"></span>
                        <span>API Documentation</span>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        // Handle form submission
        if (isset($_POST['stevegpt_save_settings'])) {
            check_admin_referer('stevegpt_settings');
            
            update_option('stevegpt_api_key', sanitize_text_field($_POST['api_key']));
            update_option('stevegpt_default_model', sanitize_text_field($_POST['default_model']));
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $api_key = get_option('stevegpt_api_key', '');
        $default_model = get_option('stevegpt_default_model', 'claude-sonnet-4-6');
        
        ?>
        <div class="wrap stevegpt-admin">
            <h1 class="stevegpt-page-title">
                <span class="stevegpt-icon">⚙️</span>
                SteveGPT Settings
            </h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('stevegpt_settings'); ?>
                
                <div class="stevegpt-section">
                    <h2>API Configuration</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api_key">Anthropic API Key</label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="api_key" 
                                       name="api_key" 
                                       value="<?php echo esc_attr($api_key); ?>" 
                                       class="regular-text"
                                       autocomplete="off">
                                <p class="description">
                                    Get your API key from the 
                                    <a href="https://console.anthropic.com/settings/keys" target="_blank">Anthropic Console</a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="default_model">Default Model</label>
                            </th>
                            <td>
                                <select id="default_model" name="default_model">
                                    <optgroup label="Claude 4 (Latest)">
                                        <option value="claude-opus-4-6" <?php selected($default_model, 'claude-opus-4-6'); ?>>
                                            Claude Opus 4.6 - Best quality ($3/$15 per 1M tokens)
                                        </option>
                                        <option value="claude-sonnet-4-6" <?php selected($default_model, 'claude-sonnet-4-6'); ?>>
                                            Claude Sonnet 4.6 - Recommended ($0.30/$1.50 per 1M tokens)
                                        </option>
                                        <option value="claude-haiku-4-5-20251001" <?php selected($default_model, 'claude-haiku-4-5-20251001'); ?>>
                                            Claude Haiku 4.5 - Fastest & cheapest ($0.10/$0.50 per 1M tokens)
                                        </option>
                                    </optgroup>
                                    <optgroup label="Claude 3.5">
                                        <option value="claude-3-5-sonnet-20241022" <?php selected($default_model, 'claude-3-5-sonnet-20241022'); ?>>
                                            Claude 3.5 Sonnet
                                        </option>
                                        <option value="claude-3-5-haiku-20241022" <?php selected($default_model, 'claude-3-5-haiku-20241022'); ?>>
                                            Claude 3.5 Haiku
                                        </option>
                                    </optgroup>
                                </select>
                                <p class="description">Default model for API calls (can be overridden per chatbot)</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" name="stevegpt_save_settings" class="button button-primary stevegpt-btn-primary">
                        Save Settings
                    </button>
                </p>
            </form>
            
            <div class="stevegpt-section">
                <h2>Connection Test</h2>
                <p>Test your API connection to verify everything is working:</p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=stevegpt-settings&test_connection=1'); ?>" 
                       class="button">
                        Test API Connection
                    </a>
                </p>
                
                <?php
                if (isset($_GET['test_connection'])) {
                    self::test_api_connection();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Test API connection
     */
    private static function test_api_connection() {
        try {
            $client = new SteveGPT_Client();
            
            $messages = array(
                array('role' => 'user', 'content' => 'Say "Connection successful!" if you can read this.')
            );
            
            $response = $client->chat_completion($messages);
            
            echo '<div class="notice notice-success">';
            echo '<p><strong>✅ Connection Successful!</strong></p>';
            echo '<p>API Response: ' . esc_html($response) . '</p>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>❌ Connection Failed</strong></p>';
            echo '<p>Error: ' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Get usage statistics
     */
    private static function get_usage_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'stevegpt_usage_log';
        
        // Get stats for last 30 days
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $total_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE timestamp >= %s",
            $thirty_days_ago
        ));
        
        $total_tokens = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_tokens) FROM $table WHERE timestamp >= %s",
            $thirty_days_ago
        ));
        
        $total_cost = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(cost) FROM $table WHERE timestamp >= %s",
            $thirty_days_ago
        ));
        
        $avg_tokens = $total_requests > 0 ? round($total_tokens / $total_requests) : 0;
        
        // Get breakdown by plugin
        $by_plugin = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                plugin_source,
                COUNT(*) as requests,
                SUM(total_tokens) as tokens,
                SUM(cost) as cost
            FROM $table 
            WHERE timestamp >= %s
            GROUP BY plugin_source
            ORDER BY cost DESC",
            $thirty_days_ago
        ));
        
        return array(
            'total_requests' => $total_requests ?: 0,
            'total_tokens' => $total_tokens ?: 0,
            'total_cost' => $total_cost ?: 0,
            'avg_tokens' => $avg_tokens,
            'by_plugin' => $by_plugin
        );
    }
}