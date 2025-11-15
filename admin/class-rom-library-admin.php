<?php
/**
 * ROM Library Admin Page with List Table and Bulk Actions
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class WP_Gamify_Bridge_ROM_List_Table
 *
 * Extends WP_List_Table for displaying ROMs with bulk actions.
 */
class WP_Gamify_Bridge_ROM_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'rom',
				'plural'   => 'roms',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'title'        => __( 'ROM Title', 'wp-gamify-bridge' ),
			'thumbnail'    => __( 'Preview', 'wp-gamify-bridge' ),
			'adapter'      => __( 'Adapter', 'wp-gamify-bridge' ),
			'system'       => __( 'System', 'wp-gamify-bridge' ),
			'file_size'    => __( 'File Size', 'wp-gamify-bridge' ),
			'status'       => __( 'Status', 'wp-gamify-bridge' ),
			'date'         => __( 'Date Added', 'wp-gamify-bridge' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'title'     => array( 'title', false ),
			'adapter'   => array( 'adapter', false ),
			'file_size' => array( 'file_size', false ),
			'date'      => array( 'date', true ), // Default sort.
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete'          => __( 'Delete', 'wp-gamify-bridge' ),
			'publish'         => __( 'Publish', 'wp-gamify-bridge' ),
			'draft'           => __( 'Set to Draft', 'wp-gamify-bridge' ),
			'change_adapter'  => __( 'Change Adapter', 'wp-gamify-bridge' ),
		);
	}

	/**
	 * Render checkbox column.
	 *
	 * @param array $item ROM data.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="rom[]" value="%s" />',
			$item['ID']
		);
	}

	/**
	 * Render title column.
	 *
	 * @param array $item ROM data.
	 * @return string
	 */
	public function column_title( $item ) {
		$edit_url = get_edit_post_link( $item['ID'] );

		// Fallback: build edit URL manually if get_edit_post_link() returns null/empty.
		if ( empty( $edit_url ) ) {
			$edit_url = admin_url( sprintf( 'post.php?post=%d&action=edit', $item['ID'] ) );
		}

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'delete',
					'rom'    => $item['ID'],
				)
			),
			'delete_rom_' . $item['ID']
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), __( 'Edit', 'wp-gamify-bridge' ) ),
			'delete' => sprintf( '<a href="%s" class="delete-rom" data-rom-id="%d">%s</a>', esc_url( $delete_url ), $item['ID'], __( 'Delete', 'wp-gamify-bridge' ) ),
		);

		$permalink = get_permalink( $item['ID'] );
		if ( $permalink && ! is_wp_error( $permalink ) ) {
			if ( 'publish' !== $item['post_status'] ) {
				$actions['view'] = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $permalink ), __( 'Preview', 'wp-gamify-bridge' ) );
			} else {
				$actions['view'] = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $permalink ), __( 'View', 'wp-gamify-bridge' ) );
			}
		}

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $item['post_title'] ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render thumbnail column.
	 *
	 * @param array $item ROM data.
	 * @return string
	 */
	public function column_thumbnail( $item ) {
		$adapter = get_post_meta( $item['ID'], '_retro_rom_adapter', true );

		// Get system icon/thumbnail based on adapter.
		$icon_map = array(
			'jsnes'       => '🎮', // NES
			'jsnes_snes'  => '🎮', // SNES
			'gba'         => '📱', // GBA
			'mame'        => '🕹️', // Arcade
			'retroarch'   => '🎯', // Multi-system
			'emulatorjs'  => '🌐', // Web-based
		);

		$icon = isset( $icon_map[ $adapter ] ) ? $icon_map[ $adapter ] : '🎮';

		return sprintf(
			'<span style="font-size: 32px; display: inline-block; width: 40px; height: 40px; text-align: center;">%s</span>',
			$icon
		);
	}

	/**
	 * Render adapter column.
	 *
	 * @param array $item ROM data.
	 * @return string
	 */
	public function column_adapter( $item ) {
		$adapter = get_post_meta( $item['ID'], '_retro_rom_adapter', true );

		// Handle null or empty adapter.
		if ( empty( $adapter ) ) {
			return '<span class="description">' . esc_html__( 'Unknown', 'wp-gamify-bridge' ) . '</span>';
		}

		$adapter_labels = array(
			'jsnes'       => 'JSNES (NES)',
			'jsnes_snes'  => 'jSNES (SNES)',
			'gba'         => 'GBA.js',
			'mame'        => 'MAME.js',
			'retroarch'   => 'RetroArch',
			'emulatorjs'  => 'EmulatorJS',
		);

		$label = isset( $adapter_labels[ $adapter ] ) ? $adapter_labels[ $adapter ] : ucfirst( $adapter );

		return esc_html( $label );
	}

	/**
	 * Render system column.
	 *
	 * @param array $item ROM data.
	 * @return string
	 */
	public function column_system( $item ) {
		$terms = get_the_terms( $item['ID'], 'retro_system' );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '<span class="description">' . esc_html__( 'None', 'wp-gamify-bridge' ) . '</span>';
		}

		$system_names = wp_list_pluck( $terms, 'name' );

		return esc_html( implode( ', ', $system_names ) );
	}

	/**
	 * Render file size column.
	 *
	 * @param array $item ROM data.
	 * @return string
	 */
	public function column_file_size( $item ) {
		$file_size = get_post_meta( $item['ID'], '_retro_rom_file_size', true );

		// Ensure file_size is numeric and not null/empty.
		if ( ! $file_size || ! is_numeric( $file_size ) ) {
			return '<span class="description">' . esc_html__( 'Unknown', 'wp-gamify-bridge' ) . '</span>';
		}

		return esc_html( size_format( $file_size, 2 ) );
	}

	/**
	 * Render status column.
	 *
	 * @param array $item ROM data.
	 * @return string
	 */
	public function column_status( $item ) {
		$status = $item['post_status'];

		$status_labels = array(
			'publish' => '<span class="status-publish">' . __( 'Published', 'wp-gamify-bridge' ) . '</span>',
			'draft'   => '<span class="status-draft">' . __( 'Draft', 'wp-gamify-bridge' ) . '</span>',
			'pending' => '<span class="status-pending">' . __( 'Pending', 'wp-gamify-bridge' ) . '</span>',
		);

		return isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : esc_html( ucfirst( $status ) );
	}

	/**
	 * Render date column.
	 *
	 * @param array $item ROM data.
	 * @return string
	 */
	public function column_date( $item ) {
		$date = get_the_date( 'Y-m-d H:i:s', $item['ID'] );

		// Handle invalid or missing dates.
		if ( ! $date || false === $date ) {
			return '<span class="description">' . esc_html__( 'No date', 'wp-gamify-bridge' ) . '</span>';
		}

		$formatted_date = get_the_date( '', $item['ID'] );
		$timestamp      = strtotime( $date );

		// Ensure valid timestamp.
		if ( false === $timestamp || ! $formatted_date ) {
			return '<span class="description">' . esc_html__( 'Invalid date', 'wp-gamify-bridge' ) . '</span>';
		}

		return sprintf(
			'%s<br><span class="description">%s</span>',
			esc_html( $formatted_date ),
			esc_html( human_time_diff( $timestamp, current_time( 'timestamp' ) ) . ' ago' )
		);
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// Get sorting parameters.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

		// Map orderby to post field.
		$orderby_map = array(
			'title'     => 'title',
			'date'      => 'date',
			'adapter'   => 'meta_value',
			'file_size' => 'meta_value_num',
		);

		$args = array(
			'post_type'      => 'retro_rom',
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'orderby'        => isset( $orderby_map[ $orderby ] ) ? $orderby_map[ $orderby ] : 'date',
			'order'          => in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ? $order : 'DESC',
			'post_status'    => array( 'publish', 'draft', 'pending' ),
		);

		// Add meta query for adapter/file_size sorting.
		if ( 'adapter' === $orderby ) {
			$args['meta_key'] = '_retro_rom_adapter';
		} elseif ( 'file_size' === $orderby ) {
			$args['meta_key'] = '_retro_rom_file_size';
		}

		$query = new WP_Query( $args );

		$this->items = array();

		foreach ( $query->posts as $post ) {
			$this->items[] = array(
				'ID'          => $post->ID,
				'post_title'  => $post->post_title,
				'post_status' => $post->post_status,
			);
		}

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => ceil( $query->found_posts / $per_page ),
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	/**
	 * Display when no items found.
	 */
	public function no_items() {
		esc_html_e( 'No ROMs found. Upload your first ROM to get started!', 'wp-gamify-bridge' );
	}

	/**
	 * Extra tablenav (filters, etc).
	 *
	 * @param string $which Top or bottom.
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		?>
		<div class="alignleft actions">
			<?php
			// Adapter filter.
			$manager = WP_Gamify_Bridge_Emulator_Manager::instance();
			if ( $manager ) {
				$adapters = $manager->get_adapters();
				?>
				<select name="filter_adapter">
					<option value=""><?php esc_html_e( 'All Adapters', 'wp-gamify-bridge' ); ?></option>
					<?php foreach ( $adapters as $adapter ) : ?>
						<option value="<?php echo esc_attr( $adapter->get_name() ); ?>">
							<?php echo esc_html( $adapter->get_display_name() ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php
			}

			// System filter.
			$systems = get_terms(
				array(
					'taxonomy'   => 'retro_system',
					'hide_empty' => true,
				)
			);

			if ( ! empty( $systems ) && ! is_wp_error( $systems ) ) {
				?>
				<select name="filter_system">
					<option value=""><?php esc_html_e( 'All Systems', 'wp-gamify-bridge' ); ?></option>
					<?php foreach ( $systems as $system ) : ?>
						<option value="<?php echo esc_attr( $system->term_id ); ?>">
							<?php echo esc_html( $system->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php
			}

			submit_button( __( 'Filter', 'wp-gamify-bridge' ), 'secondary', 'filter_action', false );
			?>
		</div>
		<?php
	}
}

/**
 * Class WP_Gamify_Bridge_ROM_Library_Admin
 *
 * Admin page for ROM Library with list table and bulk actions.
 */
class WP_Gamify_Bridge_ROM_Library_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var WP_Gamify_Bridge_ROM_Library_Admin
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WP_Gamify_Bridge_ROM_Library_Admin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
		add_action( 'admin_post_gamify_bulk_rom_action', array( $this, 'handle_bulk_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_head', array( $this, 'add_contextual_help' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		$hook = add_submenu_page(
			'gamify-bridge',
			__( 'ROM Library', 'wp-gamify-bridge' ),
			__( 'ROM Library', 'wp-gamify-bridge' ),
			'manage_options',
			'gamify-bridge-rom-library',
			array( $this, 'render_page' )
		);

		add_action( "load-{$hook}", array( $this, 'screen_options' ) );
	}

	/**
	 * Screen options.
	 */
	public function screen_options() {
		$option = 'per_page';
		$args   = array(
			'label'   => __( 'ROMs per page', 'wp-gamify-bridge' ),
			'default' => 20,
			'option'  => 'roms_per_page',
		);

		add_screen_option( $option, $args );
	}

	/**
	 * Add contextual help tabs.
	 */
	public function add_contextual_help() {
		$screen = get_current_screen();

		if ( ! $screen || 'gamify-bridge_page_gamify-bridge-rom-library' !== $screen->id ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'rom-library-overview',
				'title'   => __( 'Overview', 'wp-gamify-bridge' ),
				'content' => '<p>' . __( 'This screen provides access to all ROM files in your library. You can upload new ROMs, edit existing ones, and manage them using bulk actions.', 'wp-gamify-bridge' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'rom-library-uploading',
				'title'   => __( 'Uploading ROMs', 'wp-gamify-bridge' ),
				'content' => '<p>' . __( '<strong>Drag-and-Drop:</strong> Drag ROM files directly into the upload area at the top of the page.', 'wp-gamify-bridge' ) . '</p>' .
					'<p>' . __( '<strong>Supported Formats:</strong> 32 file formats including NES, SNES, GBA, N64, Genesis, PlayStation, and Arcade.', 'wp-gamify-bridge' ) . '</p>' .
					'<p>' . __( '<strong>File Size Limit:</strong> Default 10MB per file (can be changed via filter).', 'wp-gamify-bridge' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'rom-library-bulk-actions',
				'title'   => __( 'Bulk Actions', 'wp-gamify-bridge' ),
				'content' => '<p>' . __( 'Select multiple ROMs using the checkboxes, then choose an action from the Bulk Actions dropdown:', 'wp-gamify-bridge' ) . '</p>' .
					'<ul>' .
					'<li>' . __( '<strong>Delete:</strong> Permanently delete selected ROMs', 'wp-gamify-bridge' ) . '</li>' .
					'<li>' . __( '<strong>Publish:</strong> Make ROMs publicly available', 'wp-gamify-bridge' ) . '</li>' .
					'<li>' . __( '<strong>Set to Draft:</strong> Hide ROMs from public view', 'wp-gamify-bridge' ) . '</li>' .
					'<li>' . __( '<strong>Change Adapter:</strong> Change emulator adapter for multiple ROMs', 'wp-gamify-bridge' ) . '</li>' .
					'</ul>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'rom-library-migration',
				'title'   => __( 'Migration', 'wp-gamify-bridge' ),
				'content' => '<p>' . __( 'If you are migrating from the legacy Retro Game Emulator plugin:', 'wp-gamify-bridge' ) . '</p>' .
					'<ol>' .
					'<li>' . __( 'Run the migration script at: <code>/wp-content/plugins/wp-retro-emulator-gamification-bridge/migrate-legacy-roms.php</code>', 'wp-gamify-bridge' ) . '</li>' .
					'<li>' . __( 'Review migrated ROMs in this list', 'wp-gamify-bridge' ) . '</li>' .
					'<li>' . __( 'Update shortcodes from <code>[nes]</code> to <code>[retro_emulator]</code>', 'wp-gamify-bridge' ) . '</li>' .
					'</ol>' .
					'<p>' . __( 'See <strong>MIGRATION.md</strong> in the plugin directory for detailed instructions.', 'wp-gamify-bridge' ) . '</p>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'wp-gamify-bridge' ) . '</strong></p>' .
			'<p><a href="' . esc_url( admin_url( 'edit.php?post_type=retro_rom' ) ) . '">' . __( 'Edit ROMs (Standard View)', 'wp-gamify-bridge' ) . '</a></p>' .
			'<p><a href="' . esc_url( admin_url( 'admin.php?page=gamify-bridge' ) ) . '">' . __( 'Plugin Dashboard', 'wp-gamify-bridge' ) . '</a></p>'
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'gamify-bridge_page_gamify-bridge-rom-library' !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );

		// Inline script for drag-and-drop and bulk actions.
		$inline_script = "
		jQuery(document).ready(function($) {
			// Confirm delete actions.
			$('.delete-rom').on('click', function(e) {
				if (!confirm('" . esc_js( __( 'Are you sure you want to delete this ROM? This action cannot be undone.', 'wp-gamify-bridge' ) ) . "')) {
					e.preventDefault();
				}
			});

			// Bulk action adapter selector.
			$('#doaction, #doaction2').on('click', function(e) {
				var action = $(this).siblings('select[name=\"action\"]').val() || $(this).siblings('select[name=\"action2\"]').val();

				if (action === 'change_adapter') {
					e.preventDefault();
					var selectedRoms = $('input[name=\"rom[]\"]:checked').length;

					if (selectedRoms === 0) {
						alert('" . esc_js( __( 'Please select at least one ROM.', 'wp-gamify-bridge' ) ) . "');
						return;
					}

					$('#adapter-selector-modal').show();
				}
			});

			// Close modal.
			$('.modal-close').on('click', function() {
				$('#adapter-selector-modal').hide();
			});
		});
		";

		wp_add_inline_script( 'jquery', $inline_script );
	}

	/**
	 * Handle bulk actions.
	 */
	public function handle_bulk_action() {
		check_admin_referer( 'bulk-roms' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-gamify-bridge' ) );
		}

		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		$roms   = isset( $_POST['rom'] ) ? array_map( 'intval', wp_unslash( $_POST['rom'] ) ) : array();

		if ( empty( $roms ) || '-1' === $action ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		$count = 0;

		switch ( $action ) {
			case 'delete':
				foreach ( $roms as $rom_id ) {
					if ( wp_delete_post( $rom_id, true ) ) {
						$count++;
					}
				}
				$message = 'deleted';
				break;

			case 'publish':
				foreach ( $roms as $rom_id ) {
					if ( wp_update_post( array( 'ID' => $rom_id, 'post_status' => 'publish' ) ) ) {
						$count++;
					}
				}
				$message = 'published';
				break;

			case 'draft':
				foreach ( $roms as $rom_id ) {
					if ( wp_update_post( array( 'ID' => $rom_id, 'post_status' => 'draft' ) ) ) {
						$count++;
					}
				}
				$message = 'drafted';
				break;

			case 'change_adapter':
				$adapter = isset( $_POST['new_adapter'] ) ? sanitize_text_field( wp_unslash( $_POST['new_adapter'] ) ) : '';

				if ( ! empty( $adapter ) ) {
					foreach ( $roms as $rom_id ) {
						if ( update_post_meta( $rom_id, '_retro_rom_adapter', $adapter ) ) {
							$count++;
						}
					}
				}
				$message = 'adapter_changed';
				break;

			default:
				$message = 'invalid';
		}

		$redirect_url = add_query_arg(
			array(
				'message' => $message,
				'count'   => $count,
			),
			wp_get_referer()
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render the ROM Library page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$list_table = new WP_Gamify_Bridge_ROM_List_Table();
		$list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'ROM Library', 'wp-gamify-bridge' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=retro_rom' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New ROM', 'wp-gamify-bridge' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_admin_notices(); ?>
			<?php $this->render_drag_drop_uploader(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gamify_bulk_rom_action">
				<?php
				wp_nonce_field( 'bulk-roms' );
				$list_table->display();
				?>
			</form>

			<?php $this->render_adapter_selector_modal(); ?>
		</div>

		<style>
			.status-publish { color: #007017; font-weight: 600; }
			.status-draft { color: #646970; }
			.status-pending { color: #996800; }

			.drag-drop-uploader {
				border: 2px dashed #c3c4c7;
				background: #f6f7f7;
				padding: 40px 20px;
				text-align: center;
				margin: 20px 0;
				border-radius: 4px;
				transition: all 0.3s;
			}

			.drag-drop-uploader.drag-over {
				border-color: #2271b1;
				background: #f0f6fc;
			}

			.drag-drop-uploader h3 {
				margin: 0 0 10px 0;
				font-size: 18px;
			}

			.drag-drop-uploader p {
				margin: 0 0 20px 0;
				color: #646970;
			}

			#adapter-selector-modal {
				display: none;
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0,0,0,0.7);
				z-index: 100000;
			}

			#adapter-selector-modal .modal-content {
				position: absolute;
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
				background: #fff;
				padding: 20px;
				border-radius: 4px;
				min-width: 400px;
			}
		</style>
		<?php
	}

	/**
	 * Render admin notices.
	 */
	private function render_admin_notices() {
		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
		$count   = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;

		$messages = array(
			'deleted'         => sprintf( _n( '%d ROM deleted.', '%d ROMs deleted.', $count, 'wp-gamify-bridge' ), $count ),
			'published'       => sprintf( _n( '%d ROM published.', '%d ROMs published.', $count, 'wp-gamify-bridge' ), $count ),
			'drafted'         => sprintf( _n( '%d ROM set to draft.', '%d ROMs set to draft.', $count, 'wp-gamify-bridge' ), $count ),
			'adapter_changed' => sprintf( _n( '%d ROM adapter changed.', '%d ROM adapters changed.', $count, 'wp-gamify-bridge' ), $count ),
			'uploaded'        => sprintf( _n( '%d ROM uploaded successfully.', '%d ROMs uploaded successfully.', $count, 'wp-gamify-bridge' ), $count ),
		);

		if ( isset( $messages[ $message ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $messages[ $message ] )
			);
		}
	}

	/**
	 * Render drag-and-drop uploader.
	 */
	private function render_drag_drop_uploader() {
		?>
		<div class="drag-drop-uploader" id="rom-uploader">
			<h3><?php esc_html_e( 'Upload ROM Files', 'wp-gamify-bridge' ); ?></h3>
			<p><?php esc_html_e( 'Drag and drop ROM files here, or click to browse.', 'wp-gamify-bridge' ); ?></p>
			<button type="button" class="button button-primary button-hero" id="upload-rom-button">
				<?php esc_html_e( 'Select ROM Files', 'wp-gamify-bridge' ); ?>
			</button>
			<p class="description">
				<?php esc_html_e( 'Supported: NES, SNES, GBA, N64, Genesis, PlayStation, Arcade (32 formats, 10MB max)', 'wp-gamify-bridge' ); ?>
			</p>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var uploader = $('#rom-uploader');
			var frame;

			// Click to upload.
			$('#upload-rom-button').on('click', function(e) {
				e.preventDefault();

				if (frame) {
					frame.open();
					return;
				}

				frame = wp.media({
					title: '<?php echo esc_js( __( 'Upload ROM Files', 'wp-gamify-bridge' ) ); ?>',
					button: {
						text: '<?php echo esc_js( __( 'Import ROMs', 'wp-gamify-bridge' ) ); ?>'
					},
					multiple: true
				});

				frame.on('select', function() {
					// TODO: Create ROM posts for selected attachments
					var attachments = frame.state().get('selection').toJSON();
					console.log('Selected attachments:', attachments);

					alert('<?php echo esc_js( __( 'ROMs uploaded! Refresh the page to see them.', 'wp-gamify-bridge' ) ); ?>');
					window.location.reload();
				});

				frame.open();
			});

			// Drag and drop visual feedback.
			uploader.on('dragover', function(e) {
				e.preventDefault();
				$(this).addClass('drag-over');
			});

			uploader.on('dragleave', function(e) {
				e.preventDefault();
				$(this).removeClass('drag-over');
			});

			uploader.on('drop', function(e) {
				e.preventDefault();
				$(this).removeClass('drag-over');

				// Trigger media uploader with dropped files.
				$('#upload-rom-button').trigger('click');
			});
		});
		</script>
		<?php
	}

	/**
	 * Render adapter selector modal for bulk actions.
	 */
	private function render_adapter_selector_modal() {
		$manager = WP_Gamify_Bridge_Emulator_Manager::instance();
		if ( ! $manager ) {
			return;
		}

		$adapters = $manager->get_adapters();

		?>
		<div id="adapter-selector-modal">
			<div class="modal-content">
				<h2><?php esc_html_e( 'Select New Adapter', 'wp-gamify-bridge' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gamify_bulk_rom_action">
					<input type="hidden" name="action" value="change_adapter">
					<?php wp_nonce_field( 'bulk-roms' ); ?>

					<!-- Copy selected ROM IDs -->
					<div id="selected-roms-container"></div>

					<p>
						<label for="new-adapter"><?php esc_html_e( 'Choose Adapter:', 'wp-gamify-bridge' ); ?></label><br>
						<select name="new_adapter" id="new-adapter" style="width: 100%; margin-top: 10px;">
							<?php foreach ( $adapters as $adapter ) : ?>
								<option value="<?php echo esc_attr( $adapter->get_name() ); ?>">
									<?php echo esc_html( $adapter->get_display_name() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Change Adapter', 'wp-gamify-bridge' ); ?></button>
						<button type="button" class="button modal-close"><?php esc_html_e( 'Cancel', 'wp-gamify-bridge' ); ?></button>
					</p>
				</form>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#doaction, #doaction2').on('click', function(e) {
				var action = $(this).siblings('select[name="action"]').val() || $(this).siblings('select[name="action2"]').val();

				if (action === 'change_adapter') {
					// Copy selected ROM checkboxes to modal form.
					$('#selected-roms-container').empty();
					$('input[name="rom[]"]:checked').each(function() {
						$('#selected-roms-container').append(
							$('<input>').attr({
								type: 'hidden',
								name: 'rom[]',
								value: $(this).val()
							})
						);
					});
				}
			});
		});
		</script>
		<?php
	}
}
