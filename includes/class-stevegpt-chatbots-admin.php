<?php
/**
 * SteveGPT Chatbots Admin Page
 * Manage chatbot configurations
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class SteveGPT_Chatbots_Admin {
    
    /**
     * Render chatbots management page
     */
    public static function render_chatbots_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Handle form submissions
        self::handle_form_submissions();
        
        // Determine view
        $view = $_GET['view'] ?? 'list';
        $chatbot_id = $_GET['chatbot'] ?? null;
        
        switch ($view) {
            case 'edit':
                self::render_edit_chatbot($chatbot_id);
                break;
            case 'add':
                self::render_add_chatbot();
                break;
            default:
                self::render_chatbots_list();
        }
    }
    
    /**
     * Handle form submissions
     */
    private static function handle_form_submissions() {
        if (!isset($_POST['stevegpt_chatbot_action'])) {
            return;
        }
        
        check_admin_referer('stevegpt_chatbot');
        
        $action = $_POST['stevegpt_chatbot_action'];
        
        switch ($action) {
            case 'create':
                self::handle_create_chatbot();
                break;
            case 'update':
                self::handle_update_chatbot();
                break;
            case 'delete':
                self::handle_delete_chatbot();
                break;
            case 'clone':
                self::handle_clone_chatbot();
                break;
        }
    }
    
    /**
     * Handle create chatbot
     */
    private static function handle_create_chatbot() {
        $data = self::sanitize_chatbot_data($_POST);
        
        $chatbot_id = SteveGPT_Chatbot::create($data);
        
        if ($chatbot_id) {
            wp_redirect(admin_url('admin.php?page=stevegpt-chatbots&view=edit&chatbot=' . $chatbot_id . '&message=created'));
            exit;
        }
    }
    
    /**
     * Handle update chatbot
     */
    private static function handle_update_chatbot() {
        $chatbot_id = sanitize_text_field($_POST['chatbot_id']);
        $data = self::sanitize_chatbot_data($_POST);
        
        $result = SteveGPT_Chatbot::update($chatbot_id, $data);
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=stevegpt-chatbots&view=edit&chatbot=' . $chatbot_id . '&message=updated'));
            exit;
        }
    }
    
    /**
     * Handle delete chatbot
     */
    private static function handle_delete_chatbot() {
        $chatbot_id = sanitize_text_field($_POST['chatbot_id']);
        
        $result = SteveGPT_Chatbot::delete($chatbot_id);
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=stevegpt-chatbots&message=deleted'));
            exit;
        }
    }
    
    /**
     * Handle clone chatbot
     */
    private static function handle_clone_chatbot() {
        $chatbot_id = sanitize_text_field($_POST['chatbot_id']);
        
        $new_id = SteveGPT_Chatbot::clone_chatbot($chatbot_id);
        
        if ($new_id) {
            wp_redirect(admin_url('admin.php?page=stevegpt-chatbots&view=edit&chatbot=' . $new_id . '&message=cloned'));
            exit;
        }
    }
    
    /**
     * Sanitize chatbot data
     */
    private static function sanitize_chatbot_data($post_data) {
        $data = array(
            'name' => sanitize_text_field($post_data['name'] ?? ''),
            'description' => sanitize_textarea_field($post_data['description'] ?? ''),
            'instructions' => wp_kses_post($post_data['instructions'] ?? ''),
            'model' => sanitize_text_field($post_data['model'] ?? 'claude-sonnet-4-6'),
            'reasoning' => sanitize_text_field($post_data['reasoning'] ?? 'medium'),
            'max_tokens' => intval($post_data['max_tokens'] ?? 4096),
            'temperature' => floatval($post_data['temperature'] ?? 0.7),
            'input_max_length' => intval($post_data['input_max_length'] ?? 512),
            'max_messages' => intval($post_data['max_messages'] ?? 15),
            'context_max_length' => intval($post_data['context_max_length'] ?? 16384),
            'content_aware' => isset($post_data['content_aware']) ? 1 : 0,
            'is_active' => isset($post_data['is_active']) ? 1 : 0
        );
        
        // Build appearance array
        $data['appearance'] = array(
            'theme' => sanitize_text_field($post_data['appearance_theme'] ?? 'ChatGPT'),
            'avatar' => sanitize_text_field($post_data['appearance_avatar'] ?? '🤖'),
            'ai_name' => sanitize_text_field($post_data['appearance_ai_name'] ?? 'AI'),
            'start_sentence' => sanitize_text_field($post_data['appearance_start_sentence'] ?? 'How can I help?'),
            'user_name' => sanitize_text_field($post_data['appearance_user_name'] ?? 'You'),
            'send_text' => sanitize_text_field($post_data['appearance_send_text'] ?? 'Send'),
            'clear_text' => sanitize_text_field($post_data['appearance_clear_text'] ?? 'Clear')
        );
        
        // Build tools array
        $data['tools'] = array(
            'thinking' => isset($post_data['tool_thinking']) ? 1 : 0,
            'code_interpreter' => isset($post_data['tool_code_interpreter']) ? 1 : 0
        );
        
        return $data;
    }
    
    /**
     * Render chatbots list
     */
    private static function render_chatbots_list() {
        $chatbots = SteveGPT_Chatbot::get_all(false);
        
        // Show message if present
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $messages = array(
                'created' => 'Chatbot created successfully!',
                'updated' => 'Chatbot updated successfully!',
                'deleted' => 'Chatbot deleted successfully!',
                'cloned' => 'Chatbot cloned successfully!'
            );
            
            if (isset($messages[$message])) {
                echo '<div class="notice notice-success"><p>' . $messages[$message] . '</p></div>';
            }
        }
        
        ?>
        <div class="wrap stevegpt-admin">
            <h1 class="stevegpt-page-title">
                <span class="stevegpt-icon">🤖</span>
                Chatbots
            </h1>
            
            <p class="stevegpt-subtitle">Manage your AI chatbot configurations</p>
            
            <div class="stevegpt-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Your Chatbots</h2>
                    <a href="<?php echo admin_url('admin.php?page=stevegpt-chatbots&view=add'); ?>" class="button button-primary stevegpt-btn-primary">
                        ➕ Add New Chatbot
                    </a>
                </div>
                
                <?php if (empty($chatbots)): ?>
                    <p style="color: #999; text-align: center; padding: 40px;">No chatbots yet. Create your first one!</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped stevegpt-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Model</th>
                                <th>Reasoning</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chatbots as $chatbot): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($chatbot['name']); ?></strong>
                                        <?php if ($chatbot['chatbot_id'] === 'default'): ?>
                                            <span style="background: #C9A84C; color: #111; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 8px;">DEFAULT</span>
                                        <?php endif; ?>
                                        <div style="color: #888; font-size: 13px; margin-top: 4px;">
                                            ID: <code><?php echo esc_html($chatbot['chatbot_id']); ?></code>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($chatbot['model']); ?></td>
                                    <td><?php echo esc_html(ucfirst($chatbot['reasoning'])); ?></td>
                                    <td>
                                        <?php if ($chatbot['is_active']): ?>
                                            <span style="color: green;">✓ Active</span>
                                        <?php else: ?>
                                            <span style="color: gray;">✗ Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=stevegpt-chatbots&view=edit&chatbot=' . $chatbot['chatbot_id']); ?>">
                                            Edit
                                        </a>
                                        <?php if ($chatbot['chatbot_id'] !== 'default'): ?>
                                            | <a href="#" onclick="return confirm('Clone this chatbot?') && document.getElementById('clone-<?php echo $chatbot['chatbot_id']; ?>').submit();">
                                                Clone
                                            </a>
                                            | <a href="#" style="color: #a00;" onclick="return confirm('Delete this chatbot? This cannot be undone.') && document.getElementById('delete-<?php echo $chatbot['chatbot_id']; ?>').submit();">
                                                Delete
                                            </a>
                                            
                                            <form id="clone-<?php echo $chatbot['chatbot_id']; ?>" method="post" style="display: none;">
                                                <?php wp_nonce_field('stevegpt_chatbot'); ?>
                                                <input type="hidden" name="stevegpt_chatbot_action" value="clone">
                                                <input type="hidden" name="chatbot_id" value="<?php echo esc_attr($chatbot['chatbot_id']); ?>">
                                            </form>
                                            
                                            <form id="delete-<?php echo $chatbot['chatbot_id']; ?>" method="post" style="display: none;">
                                                <?php wp_nonce_field('stevegpt_chatbot'); ?>
                                                <input type="hidden" name="stevegpt_chatbot_action" value="delete">
                                                <input type="hidden" name="chatbot_id" value="<?php echo esc_attr($chatbot['chatbot_id']); ?>">
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render add chatbot form
     */
    private static function render_add_chatbot() {
        self::render_chatbot_form(null);
    }
    
    /**
     * Render edit chatbot form
     */
    private static function render_edit_chatbot($chatbot_id) {
        global $wpdb;
        
        $chatbot = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}stevegpt_chatbots WHERE chatbot_id = %s",
            $chatbot_id
        ), ARRAY_A);
        
        if (!$chatbot) {
            echo '<div class="notice notice-error"><p>Chatbot not found.</p></div>';
            return;
        }
        
        // Decode JSON fields
        $chatbot['tools'] = json_decode($chatbot['tools'], true) ?: array();
        $chatbot['appearance'] = json_decode($chatbot['appearance'], true) ?: array();
        
        self::render_chatbot_form($chatbot);
    }
    
    /**
     * Render chatbot form (add/edit)
     */
    private static function render_chatbot_form($chatbot) {
        $is_edit = !empty($chatbot);
        $title = $is_edit ? 'Edit Chatbot' : 'Add New Chatbot';
        $button_text = $is_edit ? 'Update Chatbot' : 'Create Chatbot';
        $action = $is_edit ? 'update' : 'create';
        
        // Default values
        $defaults = array(
            'chatbot_id' => '',
            'name' => '',
            'description' => '',
            'instructions' => '',
            'model' => 'claude-sonnet-4-6',
            'reasoning' => 'medium',
            'max_tokens' => 4096,
            'temperature' => 0.7,
            'input_max_length' => 512,
            'max_messages' => 15,
            'context_max_length' => 16384,
            'content_aware' => 0,
            'is_active' => 1,
            'tools' => array(),
            'appearance' => array(
                'theme' => 'ChatGPT',
                'avatar' => '🤖',
                'ai_name' => 'AI',
                'start_sentence' => 'How can I help?',
                'user_name' => 'You',
                'send_text' => 'Send',
                'clear_text' => 'Clear'
            )
        );
        
        $chatbot = $is_edit ? array_merge($defaults, $chatbot) : $defaults;
        
        // Show message if present
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $messages = array(
                'created' => 'Chatbot created successfully!',
                'updated' => 'Chatbot updated successfully!',
                'cloned' => 'Chatbot cloned successfully!'
            );
            
            if (isset($messages[$message])) {
                echo '<div class="notice notice-success"><p>' . $messages[$message] . '</p></div>';
            }
        }
        
        ?>
        <div class="wrap stevegpt-admin">
            <h1 class="stevegpt-page-title">
                <span class="stevegpt-icon">🤖</span>
                <?php echo $title; ?>
            </h1>
            
            <p class="stevegpt-subtitle">
                <a href="<?php echo admin_url('admin.php?page=stevegpt-chatbots'); ?>">← Back to Chatbots</a>
            </p>
            
            <form method="post" action="">
                <?php wp_nonce_field('stevegpt_chatbot'); ?>
                <input type="hidden" name="stevegpt_chatbot_action" value="<?php echo $action; ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="chatbot_id" value="<?php echo esc_attr($chatbot['chatbot_id']); ?>">
                <?php endif; ?>
                
                <!-- Basic Info -->
                <div class="stevegpt-section">
                    <h2>Basic Information</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="name">Chatbot Name *</label></th>
                            <td>
                                <input type="text" id="name" name="name" value="<?php echo esc_attr($chatbot['name']); ?>" class="regular-text" required>
                                <p class="description">e.g., "RAG Weekly Coach", "Personality Test Helper"</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="description">Description</label></th>
                            <td>
                                <textarea id="description" name="description" rows="3" class="large-text"><?php echo esc_textarea($chatbot['description']); ?></textarea>
                                <p class="description">What is this chatbot for?</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="instructions">Instructions (System Prompt) *</label></th>
                            <td>
                                <textarea id="instructions" name="instructions" rows="10" class="large-text" required><?php echo esc_textarea($chatbot['instructions']); ?></textarea>
                                <p class="description">The AI's personality and role. This is the system prompt that guides all responses.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="is_active">Status</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="is_active" name="is_active" value="1" <?php checked($chatbot['is_active'], 1); ?>>
                                    Active
                                </label>
                                <p class="description">Inactive chatbots cannot be used</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- AI Model Settings -->
                <div class="stevegpt-section">
                    <h2>AI Model Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="model">Model</label></th>
                            <td>
                                <select id="model" name="model">
                                    <optgroup label="Claude 4 (Latest)">
                                        <option value="claude-opus-4-6" <?php selected($chatbot['model'], 'claude-opus-4-6'); ?>>Claude Opus 4.6 - Best quality ($3/$15)</option>
                                        <option value="claude-sonnet-4-6" <?php selected($chatbot['model'], 'claude-sonnet-4-6'); ?>>Claude Sonnet 4.6 - Recommended ($0.30/$1.50)</option>
                                        <option value="claude-haiku-4-5-20251001" <?php selected($chatbot['model'], 'claude-haiku-4-5-20251001'); ?>>Claude Haiku 4.5 - Fastest ($0.10/$0.50)</option>
                                    </optgroup>
                                    <optgroup label="Claude 3.5">
                                        <option value="claude-3-5-sonnet-20241022" <?php selected($chatbot['model'], 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet</option>
                                        <option value="claude-3-5-haiku-20241022" <?php selected($chatbot['model'], 'claude-3-5-haiku-20241022'); ?>>Claude 3.5 Haiku</option>
                                    </optgroup>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="reasoning">Reasoning Level</label></th>
                            <td>
                                <select id="reasoning" name="reasoning">
                                    <option value="none" <?php selected($chatbot['reasoning'], 'none'); ?>>None</option>
                                    <option value="low" <?php selected($chatbot['reasoning'], 'low'); ?>>Low</option>
                                    <option value="medium" <?php selected($chatbot['reasoning'], 'medium'); ?>>Medium</option>
                                    <option value="high" <?php selected($chatbot['reasoning'], 'high'); ?>>High</option>
                                </select>
                                <p class="description">Extended thinking for complex tasks (costs more tokens)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="max_tokens">Max Tokens</label></th>
                            <td>
                                <input type="number" id="max_tokens" name="max_tokens" value="<?php echo esc_attr($chatbot['max_tokens']); ?>" min="100" max="200000">
                                <p class="description">Maximum response length (4096 = ~3000 words)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="temperature">Temperature</label></th>
                            <td>
                                <input type="number" id="temperature" name="temperature" value="<?php echo esc_attr($chatbot['temperature']); ?>" min="0" max="1" step="0.1">
                                <p class="description">0 = focused, 1 = creative (default: 0.7)</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Thresholds -->
                <div class="stevegpt-section">
                    <h2>Thresholds</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="input_max_length">Input Max Length</label></th>
                            <td>
                                <input type="number" id="input_max_length" name="input_max_length" value="<?php echo esc_attr($chatbot['input_max_length']); ?>" min="50" max="10000">
                                <p class="description">Max characters user can input</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="max_messages">Max Messages</label></th>
                            <td>
                                <input type="number" id="max_messages" name="max_messages" value="<?php echo esc_attr($chatbot['max_messages']); ?>" min="1" max="100">
                                <p class="description">Max conversation history sent to AI</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="context_max_length">Context Max Length</label></th>
                            <td>
                                <input type="number" id="context_max_length" name="context_max_length" value="<?php echo esc_attr($chatbot['context_max_length']); ?>" min="1000" max="200000">
                                <p class="description">Truncate long messages to this length</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Appearance -->
                <div class="stevegpt-section">
                    <h2>Appearance</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="appearance_theme">Theme</label></th>
                            <td>
                                <select id="appearance_theme" name="appearance_theme">
                                    <option value="ChatGPT" <?php selected($chatbot['appearance']['theme'] ?? 'ChatGPT', 'ChatGPT'); ?>>ChatGPT</option>
                                    <option value="Modern" <?php selected($chatbot['appearance']['theme'] ?? '', 'Modern'); ?>>Modern</option>
                                    <option value="Classic" <?php selected($chatbot['appearance']['theme'] ?? '', 'Classic'); ?>>Classic</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="appearance_avatar">Avatar (Emoji)</label></th>
                            <td>
                                <input type="text" id="appearance_avatar" name="appearance_avatar" value="<?php echo esc_attr($chatbot['appearance']['avatar'] ?? '🤖'); ?>" maxlength="4" style="width: 80px; font-size: 24px;">
                                <p class="description">e.g., 🧑‍🏫 🎯 🎭 🤖 💬 🌟</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="appearance_ai_name">AI Name</label></th>
                            <td>
                                <input type="text" id="appearance_ai_name" name="appearance_ai_name" value="<?php echo esc_attr($chatbot['appearance']['ai_name'] ?? 'AI'); ?>" class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="appearance_start_sentence">Start Sentence</label></th>
                            <td>
                                <input type="text" id="appearance_start_sentence" name="appearance_start_sentence" value="<?php echo esc_attr($chatbot['appearance']['start_sentence'] ?? 'How can I help?'); ?>" class="regular-text">
                                <p class="description">Placeholder text in input field</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Shortcode -->
                <?php if ($is_edit): ?>
                <div class="stevegpt-section">
                    <h2>Shortcode</h2>
                    <p>Use this shortcode to display this chatbot on any page:</p>
                    <input type="text" value='[stevegpt_chatbot id="<?php echo esc_attr($chatbot['chatbot_id']); ?>"]' readonly class="large-text" onclick="this.select();" style="font-family: monospace; background: #f0f0f0;">
                </div>
                <?php endif; ?>
                
                <p class="submit">
                    <button type="submit" class="button button-primary stevegpt-btn-primary">
                        <?php echo $button_text; ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=stevegpt-chatbots'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }
}