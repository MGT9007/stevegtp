<?php
/**
 * SteveGPT Admin Page
 * Settings and dashboard for SteveGPT
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
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get usage statistics
        $client = new SteveGPT_Client();
        $stats = $client->get_usage_stats(30);
        
        ?>
        <div class="wrap stevegpt-admin">
            <h1 class="stevegpt-page-title">
                <span class="stevegpt-icon">💬</span>
                SteveGPT Dashboard
            </h1>
            
            <p class="stevegpt-subtitle">AI Integration for My Future Self Digital</p>
            
            <div class="stevegpt-stats-grid">
                <div class="stevegpt-stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['total_requests']); ?></div>
                        <div class="stat-label">Total Requests</div>
                        <div class="stat-meta">Last 30 days</div>
                    </div>
                </div>
                
                <div class="stevegpt-stat-card">
                    <div class="stat-icon">🎯</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['total_tokens']); ?></div>
                        <div class="stat-label">Total Tokens</div>
                        <div class="stat-meta">Input + Output</div>
                    </div>
                </div>
                
                <div class="stevegpt-stat-card highlight">
                    <div class="stat-icon">💰</div>
                    <div class="stat-content">
                        <div class="stat-value">$<?php echo number_format($stats['total_cost'], 2); ?></div>
                        <div class="stat-label">Total Cost</div>
                        <div class="stat-meta">Direct API billing</div>
                    </div>
                </div>
                
                <div class="stevegpt-stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['avg_tokens_per_request']); ?></div>
                        <div class="stat-label">Avg Tokens/Request</div>
                        <div class="stat-meta">Efficiency metric</div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($stats['by_plugin'])): ?>
            <div class="stevegpt-section">
                <h2>Usage by Plugin</h2>
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
                        <?php foreach ($stats['by_plugin'] as $plugin_stats): 
                            $percentage = ($stats['total_cost'] > 0) 
                                ? ($plugin_stats['cost'] / $stats['total_cost']) * 100 
                                : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($plugin_stats['plugin_source']); ?></strong></td>
                            <td><?php echo number_format($plugin_stats['requests']); ?></td>
                            <td><?php echo number_format($plugin_stats['tokens']); ?></td>
                            <td>$<?php echo number_format($plugin_stats['cost'], 4); ?></td>
                            <td><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="stevegpt-section">
                <h2>Quick Links</h2>
                <div class="stevegpt-quick-links">
                    <a href="<?php echo admin_url('admin.php?page=stevegpt-settings'); ?>" class="stevegpt-btn">
                        ⚙️ Settings
                    </a>
                    <a href="https://console.anthropic.com/" target="_blank" class="stevegpt-btn secondary">
                        🔑 Anthropic Console
                    </a>
                    <a href="https://docs.anthropic.com/" target="_blank" class="stevegpt-btn secondary">
                        📚 API Documentation
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
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Handle form submission
        if (isset($_POST['stevegpt_save_settings']) && check_admin_referer('stevegpt_settings')) {
            update_option('stevegpt_api_key', sanitize_text_field($_POST['stevegpt_api_key']));
            update_option('stevegpt_model', sanitize_text_field($_POST['stevegpt_model']));
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        // Handle connection test
        $test_result = null;
        if (isset($_POST['stevegpt_test_connection']) && check_admin_referer('stevegpt_test')) {
            $client = new SteveGPT_Client();
            $test_result = $client->test_connection();
        }
        
        // Get current settings
        $api_key = get_option('stevegpt_api_key', '');
        $model = get_option('stevegpt_model', 'claude-opus-4-6');
        
        ?>
        <div class="wrap stevegpt-admin">
            <h1 class="stevegpt-page-title">
                <span class="stevegpt-icon">⚙️</span>
                SteveGPT Settings
            </h1>
            
            <p class="stevegpt-subtitle">Configure your Anthropic Claude API connection</p>
            
            <?php if ($test_result): ?>
                <?php if ($test_result['success']): ?>
                    <div class="notice notice-success stevegpt-notice">
                        <p><strong>✅ <?php echo esc_html($test_result['message']); ?></strong></p>
                        <p>Model: <code><?php echo esc_html($test_result['model']); ?></code></p>
                        <p>Response: "<?php echo esc_html($test_result['response']); ?>"</p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-error stevegpt-notice">
                        <p><strong>❌ <?php echo esc_html($test_result['message']); ?></strong></p>
                        <p><?php echo esc_html($test_result['error']); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="stevegpt-section">
                <h2>API Configuration</h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('stevegpt_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="stevegpt_api_key">Anthropic API Key</label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="stevegpt_api_key" 
                                       name="stevegpt_api_key" 
                                       value="<?php echo esc_attr($api_key); ?>" 
                                       class="regular-text"
                                       placeholder="sk-ant-api03-...">
                                <p class="description">
                                    Get your API key from <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="stevegpt_model">Claude Model</label>
                            </th>
                            <td>
                                <select id="stevegpt_model" name="stevegpt_model">
                                    <optgroup label="Claude 4 (Latest)">
                                        <option value="claude-opus-4-6" <?php selected($model, 'claude-opus-4-6'); ?>>
                                            Claude Opus 4.6 - Best quality ($3/$15 per 1M tokens)
                                        </option>
                                        <option value="claude-sonnet-4-6" <?php selected($model, 'claude-sonnet-4-6'); ?>>
                                            Claude Sonnet 4.6 - Recommended ($0.30/$1.50 per 1M tokens)
                                        </option>
                                        <option value="claude-haiku-4-5-20251001" <?php selected($model, 'claude-haiku-4-5-20251001'); ?>>
                                            Claude Haiku 4.5 - Fastest ($0.10/$0.50 per 1M tokens)
                                        </option>
                                    </optgroup>
                                    <optgroup label="Claude 3.5">
                                        <option value="claude-3-5-sonnet-20241022" <?php selected($model, 'claude-3-5-sonnet-20241022'); ?>>
                                            Claude 3.5 Sonnet
                                        </option>
                                        <option value="claude-3-5-haiku-20241022" <?php selected($model, 'claude-3-5-haiku-20241022'); ?>>
                                            Claude 3.5 Haiku
                                        </option>
                                    </optgroup>
                                </select>
                                <p class="description">
                                    <strong>Recommended:</strong> Claude Sonnet 4.6 for production (90% quality of Opus at 10x lower cost)
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="stevegpt_save_settings" class="button button-primary stevegpt-btn-primary">
                            Save Settings
                        </button>
                    </p>
                </form>
            </div>
            
            <div class="stevegpt-section">
                <h2>Test Connection</h2>
                <p>Send a test request to verify your API key and model configuration.</p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('stevegpt_test'); ?>
                    <button type="submit" name="stevegpt_test_connection" class="button stevegpt-btn">
                        🧪 Test API Connection
                    </button>
                </form>
            </div>
            
            <div class="stevegpt-section">
                <h2>Plugin Information</h2>
                <table class="form-table">
                    <tr>
                        <th>Version</th>
                        <td><code><?php echo STEVEGPT_VERSION; ?></code></td>
                    </tr>
                    <tr>
                        <th>Provider</th>
                        <td>Anthropic Claude</td>
                    </tr>
                    <tr>
                        <th>MWAI Compatibility</th>
                        <td>✅ Drop-in replacement via <code>$GLOBALS['stevegpt']</code></td>
                    </tr>
                    <tr>
                        <th>Current Model</th>
                        <td><code><?php echo esc_html($model); ?></code></td>
                    </tr>
                    <tr>
                        <th>API Status</th>
                        <td><?php echo !empty($api_key) ? '🟢 API Key Configured' : '🔴 API Key Missing'; ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="stevegpt-section">
                <h2>Pricing Information</h2>
                <table class="wp-list-table widefat fixed striped stevegpt-table">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Input Cost</th>
                            <th>Output Cost</th>
                            <th>Best For</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Claude Opus 4.6</strong></td>
                            <td>$3.00 / 1M tokens</td>
                            <td>$15.00 / 1M tokens</td>
                            <td>Highest quality, complex tasks</td>
                        </tr>
                        <tr class="highlight">
                            <td><strong>Claude Sonnet 4.6</strong> ⭐</td>
                            <td>$0.30 / 1M tokens</td>
                            <td>$1.50 / 1M tokens</td>
                            <td><strong>Production recommended</strong></td>
                        </tr>
                        <tr>
                            <td><strong>Claude Haiku 4.5</strong></td>
                            <td>$0.10 / 1M tokens</td>
                            <td>$0.50 / 1M tokens</td>
                            <td>Fast, simple tasks</td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="description">
                    <strong>Example:</strong> 1,000 students × 6 weeks with Sonnet 4.6 = ~$23.49 total cost
                </p>
            </div>
        </div>
        <?php
    }
}
