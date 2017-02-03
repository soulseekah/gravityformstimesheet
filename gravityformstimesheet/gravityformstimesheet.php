<?php

	/*
		Plugin Name: Gravity Forms Timesheet Addon
		Description: Adds a new field type to Gravity Forms called 'Timesheet', which allows users to provide time and date ranges.
		Author: Gennady Kovshenin
		Author URI: http://codeseekah.com
		Version: 0.2
	*/

	if ( !class_exists( 'GFForms' ) || !defined( 'WPINC' ) )
		return;

	class GF_Field_Timesheet extends GF_Field {
		public $type = 'timesheet';

		private static $_translation_domain = 'gravityformstimesheet';

		public function get_form_editor_field_settings() {
			return array(
				'label_setting',
				'admin_label_setting',
				'rules_setting',
				'date_format_setting',
				'time_format_setting',
				'maxrows_setting',
				'visibility_setting',
				'description_setting',
				'css_class_setting',
				'add_icon_url_setting',
				'delete_icon_url_setting',
			);
		}

		public function is_conditional_logic_supported() {
			return false;
		}

		public function get_form_editor_field_title() {
			return esc_attr__( 'Timesheet', 'gravityformstimesheet' );
		}

		public function validate( $value, $form ) {
			$chunksize = $this->timeFormat == '24' ? 6 : 8;
			$values = array_chunk( $value, $chunksize );

			$value = array();
			foreach ( $values as $chunk ) {
				while ( count( $chunk ) < $chunksize ) {
					$chunk []= '';
				}
				$value = array(
					'date' => array_shift( $chunk ),
					'checkin_hour' => array_shift( $chunk ), 'checkin_minute' => array_shift( $chunk ), 'checkin_24' => $this->timeFormat == '24' ? '' : array_shift( $chunk ),
					'checkout_hour' => array_shift( $chunk ), 'checkout_minute' => array_shift( $chunk ), 'checkout_24' => $this->timeFormat == '24' ? '' : array_shift( $chunk ),
					'comment' => array_shift( $chunk )
				);

				if ( !$this->isRequired ) {
					if ( empty( $value['date'] ) && empty( $value['checkin_hour'] ) && empty( $value['checkin_minute'] ) && empty( $value['checkout_hour'] ) && empty( $value['checkout_minute'] ) && empty( $value['comment'] ) ) {
						continue;
					}
				}

				$date_info = GFCommon::parse_date( $value['date'], $this->dateFormat );
				if ( GFCommon::is_empty_array( $date_info ) || !checkdate( $date_info['month'], $date_info['day'], $date_info['year'] ) ) {
					$this->failed_validation = true;
					$this->validation_message = __( 'Invalid date or date format', self::$_translation_domain );
					return;
				}

				if ( !is_numeric( $value['checkin_hour'] ) || !is_numeric( $value['checkin_minute'] )
					|| !is_numeric( $value['checkout_hour'] ) || !is_numeric( $value['checkout_minute'] )
				) {

					$this->failed_validation = true;
					$this->validation_message = __( 'Invalid time or time format', self::$_translation_domain );
					return;
				}

				if ( $this->timeFormat != '24' ) {
					if ( $value['checkin_24'] == 'am' && intval( $value['checkin_hour'] ) == 12 ) $value['checkin_hour'] = 0;
					if ( $value['checkin_24'] == 'pm' && intval( $value['checkin_hour'] ) != 12 ) $value['checkin_hour'] += 12;
					if ( $value['checkout_24'] == 'pm' && intval( $value['checkout_hour'] ) != 12 ) $value['checkout_hour'] += 12;
				}

				if (
					( intval( $value['checkin_hour'] ) < 0 || intval( $value['checkin_hour'] ) > 23 )
					|| ( intval( $value['checkout_hour'] ) < 0 || intval( $value['checkout_hour'] ) > 23 )
					|| ( intval( $value['checkin_minute'] ) < 0 || intval( $value['checkin_minute'] ) > 59 )
					|| ( intval( $value['checkout_minute'] ) < 0 || intval( $value['checkout_minute'] ) > 59 )
				)  {
					$this->failed_validation = true;
					$this->validation_message = __( 'Invalid time or time format', self::$_translation_domain );
					return;
				}

				if ( intval( $value['checkin_hour'] ) >= intval( $value['checkout_hour'] ) ) {
					if ( intval( $value['checkin_minute'] ) >= intval( $value['checkout_minute'] ) ) {
						$this->failed_validation = true;
						$this->validation_message = __( 'Invalid time or time format', self::$_translation_domain );
						return;
					}
				}
			}
		}


		/**
		 * Rendering the UI of the timesheet field.
		 */
		public function get_field_input( $form, $value = '', $entry = null ) {

			wp_enqueue_style( 'gforms_datepicker_css', GFCommon::get_base_url() . '/css/datepicker.css', null, GFCommon::$version );
			wp_enqueue_script( 'gform_datepicker_init' );

			if ( is_admin() ) {
				ob_start();

				echo esc_html__( 'Timesheet fields are not editable.' );
				?>
					<input type="hidden" name="input_<?php echo esc_attr( $this->id ); ?>" value="<?php echo esc_attr( $value ); ?>">
				<?php

				return ob_get_clean();
			}

			if ( !empty( $value ) )
				$value = maybe_unserialize( $value );

			if ( !is_array( $value ) )
				$value = array( array(
					'date' => '',
					'checkin_hour' => '', 'checkin_minute' => '', 'checkin_24' => '',
					'checkout_hour' => '', 'checkout_minute' => '', 'checkout_24' => '',
					'comment' => ''
				) );
			else {
				$value = $this->unserialize( $value );
			}

			ob_start();
			?>
				<div class="ginput_container ginput_timesheet" id="ginput_timesheet-<?php echo esc_attr( $this->id ); ?>">
					<table class="gfield_timesheet">
						<colgroup>
							<col class="gfield_timesheet_column gfield_timesheet_column_date">
							<col class="gfield_timesheet_column gfield_timesheet_column_checkin">
							<col class="gfield_timesheet_column gfield_timesheet_column_checkout">
							<col class="gfield_timesheet_column gfield_timesheet_column_comment">
						</colgroup>

						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', self::$_translation_domain ); ?></th>
								<th><?php esc_html_e( 'Check in', self::$_translation_domain ); ?></th>
								<th><?php esc_html_e( 'Check out', self::$_translation_domain ); ?></th>
								<th><?php esc_html_e( 'Comment', self::$_translation_domain ); ?></th>
								<th></th>
							</tr>
						</thead>

						<tbody>
							<?php
								$rownum = 1;
								$format = empty( $this->dateFormat ) ? 'mdy' : esc_attr( $this->dateFormat );
								$add_icon = $this->addIconUrl ? : GFCommon::get_base_url() . '/images/add.png';
								$delete_icon = $this->deleteIconUrl ? : GFCommon::get_base_url() . '/images/remove.png';
								$maxrows = !empty( $this->maxRows ) && is_numeric( $this->maxRows ) && $this->maxRows > -1 ? $this->maxRows : 0;

								foreach ( $value as $item ):
									?>
										<tr class="gfield_timesheet_row">
											<td class="gfield_timesheet_cell gfield_timesheet_cell_date">
												<input type="text" class="datepicker <?php echo $format; ?>" name="input_<?php echo esc_attr( $this->id ); ?>[]" value="<?php echo esc_attr( $item['date'] ); ?>">
											</td>
											<td class="gfield_timesheet_cell gfield_timesheet_cell_checkin">
												<?php $this->display_time_field( array(
														'hour' => $item['checkin_hour'],
														'minute' => $item['checkin_minute'],
														'24' => $item['checkin_24']
													) ); ?>
											</td>
											<td class="gfield_timesheet_cell gfield_timesheet_cell_checkout">
												<?php $this->display_time_field( array(
														'hour' => $item['checkout_hour'],
														'minute' => $item['checkout_minute'],
														'24' => $item['checkout_24']
													) ); ?>
											</td>
											<td class="gfield_timesheet_cell gfield_timesheet_cell_comment">
												<input type="text" name="input_<?php echo esc_attr( $this->id ); ?>[]" value="<?php echo esc_attr( $item['comment'] ); ?>">
											</td>
											<td class="gfield_timesheet_cell gfield_timesheet_icons">
												<img src="<?php echo esc_attr( $add_icon ); ?>" class="add_list_item" style="cursor: pointer; margin: 0 3px;">
												<img src="<?php echo esc_attr( $delete_icon ); ?>" class="delete_list_item" style="cursor: pointer;">
											</td>
										</tr>
									<?php
									$rownum++;
								endforeach;
							?>
						</tbody>
					</table>

					<script>
						jQuery( document ).ready( function() {
							var timesheet = jQuery( '#ginput_timesheet-<?php echo esc_attr( $this->id ); ?>' );
							var maxrows = <?php echo json_encode( $maxrows ); ?>;

							var hide_buttons = function() {
								if ( timesheet.find( 'tr.gfield_timesheet_row' ).length < 2 ) timesheet.find( '.delete_list_item' ).css( 'visibility', 'hidden' );
								else timesheet.find( '.delete_list_item' ).css( 'visibility', '' );
								if ( maxrows < 1 ) return;
								if ( timesheet.find( 'tr.gfield_timesheet_row' ).length >= maxrows ) timesheet.find( '.add_list_item' ).css( 'visibility', 'hidden' );
								else timesheet.find( '.add_list_item' ).css( 'visibility', '' );
							};

							hide_buttons();

							timesheet.on( 'click', '.add_list_item', function( e ) {
								e.preventDefault();
								if ( maxrows > 0 && timesheet.find( 'tr.gfield_timesheet_row' ).length >= maxrows ) return false;
								var tr = jQuery( e.target ).parents( 'tr.gfield_timesheet_row' ).clone();
								tr.find( '.gfield_timesheet_cell_date input' ).removeClass( 'hasDatepicker' ).attr( 'id', '' );
								tr.find( 'input' ).val( '' );
								timesheet.find( 'tbody'	).append( tr );
								hide_buttons();
								gformInitDatepicker();
								return false;
							} );

							timesheet.on( 'click', '.delete_list_item', function( e ) {
								e.preventDefault();
								if ( timesheet.find( 'tr.gfield_timesheet_row' ).length < 2 ) return false;
								jQuery( e.target ).parents( 'tr.gfield_timesheet_row' ).remove();
								hide_buttons();
								return false;
							} );
						} );
					</script>
				</div>
			<?php
			
			return ob_get_clean();
		}

		/**
		 * Serialize the timesheet data when creating, no serialization when editing.
		 */
		public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
			
			$is_form_editor = GFCommon::is_form_editor();
			$is_entry_detail = GFCommon::is_entry_detail();
			$is_admin = $is_form_editor || $is_entry_detail;
			
			if ( $is_admin ) {
				return $value;
			}

			return serialize( $value );
		}

		public function sanitize_settings() {
			parent::sanitize_settings();
			$this->calendarIconType = wp_strip_all_tags( $this->calendarIconType );
			$this->calendarIconUrl  = wp_strip_all_tags( $this->calendarIconUrl );
			if ( ! $this->dateFormat || ! in_array( $this->dateFormat, array( 'mdy', 'dmy', 'dmy_dash', 'dmy_dot', 'ymd_slash', 'ymd_dash', 'ymd_dot' ) ) ) {
				$this->dateFormat = 'mdy';
			}
		}

		/**
		 * Render the lead view of the timesheet field.
		 */
		public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
			$value = maybe_unserialize( $value );
			$chunksize = $this->timeFormat == '24' ? 6 : 8;
			$values = array_chunk( $value, $chunksize );
			$value = array();
			foreach ( $values as $chunk ) {
				while ( count( $chunk ) < $chunksize ) {
					$chunk []= '';
				}
				$entry = array(
					'date' => array_shift( $chunk ),
					'checkin_hour' => array_shift( $chunk ), 'checkin_minute' => array_shift( $chunk ), 'checkin_24' => $this->timeFormat == '24' ? '' : array_shift( $chunk ),
					'checkout_hour' => array_shift( $chunk ), 'checkout_minute' => array_shift( $chunk ), 'checkout_24' => $this->timeFormat == '24' ? '' : array_shift( $chunk ),
					'comment' => array_shift( $chunk )
				);
				if ( !empty( $entry['date'] ) )
					$value []= $entry;
			}

			ob_start();
			?>
				<table>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', self::$_translation_domain ); ?></th>
							<th><?php esc_html_e( 'Check in', self::$_translation_domain ); ?></th>
							<th><?php esc_html_e( 'Check out', self::$_translation_domain ); ?></th>
							<th><?php esc_html_e( 'Comment', self::$_translation_domain ); ?></th>
						</tr>
					<thead>
					<tbody>
						<?php
							$minutes = 0;
							foreach ( $value as $entry ):
								?>
									<tr>
										<td><?php echo $entry['date']; ?></td>
										<td><?php printf( '%02d:%02d%s', $entry['checkin_hour'], $entry['checkin_minute'], $this->timeFormat == '24' ? '' : ' ' .$entry['checkin_24'] ); ?></td>
										<td><?php printf( '%02d:%02d%s', $entry['checkout_hour'], $entry['checkout_minute'], $this->timeFormat == '24' ? '' : ' ' .$entry['checkout_24'] ); ?></td>
										<td><?php echo esc_html( $entry['comment'] ); ?></td>
									</tr>
								<?php
								if ( $this->timeFormat != '24' ) {
									if ( $entry['checkin_24'] == 'am' && intval( $entry['checkin_hour'] ) == 12 ) $entry['checkin_hour'] = 0;
									if ( $entry['checkin_24'] == 'pm' && $entry['checkin_hour'] != 12 ) $entry['checkin_hour'] += 12;
									if ( $entry['checkout_24'] == 'pm' && $entry['checkout_hour'] != 12 ) $entry['checkout_hour'] += 12;
								}
								
								$minutes += ( ( $entry['checkout_hour'] * 60 ) + $entry['checkout_minute'] ) - ( ( $entry['checkin_hour'] * 60 ) + $entry['checkin_minute'] );
							endforeach;
						?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="2"><?php printf( esc_html__( 'Total entries: %d', self::$_translation_domain ), count( $value ) ); ?></td>
							<td colspan="2"><?php printf( esc_html__( 'Total time: %02d:%02d', self::$_translation_domain ), $minutes / 60, $minutes % 60);?></td>
						</tr>
					</tfoot>
				</table>
			<?php

			return ob_get_clean();
		}

		/**
		 * The title in the list of leads.
		 */
		public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
			$value = $this->unserialize( html_entity_decode( $value ) );
			$minutes = 0;
			foreach ( $value as $entry ):
				if ( $this->timeFormat != '24' ) {
					if ( $entry['checkin_24'] == 'am' && intval( $entry['checkin_hour'] ) == 12 ) $entry['checkin_hour'] = 0;
					if ( $entry['checkin_24'] == 'pm' && $entry['checkin_hour'] != 12 ) $entry['checkin_hour'] += 12;
					if ( $entry['checkout_24'] == 'pm' && $entry['checkout_hour'] != 12 ) $entry['checkout_hour'] += 12;
				}
				$minutes += ( ( $entry['checkout_hour'] * 60 ) + $entry['checkout_minute'] ) - ( ( $entry['checkin_hour'] * 60 ) + $entry['checkin_minute'] );
			endforeach;

			return sprintf( '%d entries, %02d:%02d total', count( $value ), $minutes/60, $minutes % 60 );
		}

		/**
		 * Renders the timesheet table wherever used; mostly email
		 * notifications and confirmations. But who knows where else...
		 */
		public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
			$value = $this->unserialize( $raw_value );
			ob_start();
			$minutes = 0;
			?>
				<table>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', self::$_translation_domain ); ?></th>
							<th><?php esc_html_e( 'Check in', self::$_translation_domain ); ?></th>
							<th><?php esc_html_e( 'Check out', self::$_translation_domain ); ?></th>
							<th><?php esc_html_e( 'Comment', self::$_translation_domain ); ?></th>
						</tr>
					<thead>
					<tbody>
						<?php
							$minutes = 0;
							foreach ( $value as $entry ):
								?>
									<tr>
										<td><?php echo $entry['date']; ?></td>
										<td><?php printf( '%02d:%02d%s', $entry['checkin_hour'], $entry['checkin_minute'], $this->timeFormat == '24' ? '' : ' ' .$entry['checkin_24'] ); ?></td>
										<td><?php printf( '%02d:%02d%s', $entry['checkout_hour'], $entry['checkout_minute'], $this->timeFormat == '24' ? '' : ' ' .$entry['checkout_24'] ); ?></td>
										<td><?php echo esc_html( $entry['comment'] ); ?></td>
									</tr>
								<?php
								if ( $this->timeFormat != '24' ) {
									if ( $entry['checkin_24'] == 'am' && intval( $entry['checkin_hour'] ) == 12 ) $entry['checkin_hour'] = 0;
									if ( $entry['checkin_24'] == 'pm' && $entry['checkin_hour'] != 12 ) $entry['checkin_hour'] += 12;
									if ( $entry['checkout_24'] == 'pm' && $entry['checkout_hour'] != 12 ) $entry['checkout_hour'] += 12;
								}
								
								$minutes += ( ( $entry['checkout_hour'] * 60 ) + $entry['checkout_minute'] ) - ( ( $entry['checkin_hour'] * 60 ) + $entry['checkin_minute'] );
							endforeach;
						?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="2"><?php printf( esc_html__( 'Total entries: %d', self::$_translation_domain ), count( $value ) ); ?></td>
							<td colspan="2"><?php printf( esc_html__( 'Total time: %02d:%02d', self::$_translation_domain ), $minutes / 60, $minutes % 60);?></td>
						</tr>
					</tfoot>
				</table>
			<?php

			return ob_get_clean();
		}

		/**
		 * The HH:MM (am/pm) set markup.
		 */
		private function display_time_field( $value ) {
			?>
			<div class="clear-multi">
				<div class="gfield_time_hour ginput_container">
					<input type="text" maxlength="2" name="input_<?php echo esc_attr( $this->id ); ?>[]" value="<?php echo esc_attr( $value['hour'] ); ?>">
					:
					<label><?php echo esc_html( __( 'HH', 'gravityforms' ) ); ?></label>
				</div>
				<div class="gfield_time_minute ginput_container">
					<input type="text" maxlength="2" name="input_<?php echo esc_attr( $this->id ); ?>[]" value="<?php echo esc_attr( $value['minute'] ); ?>">
					<label><?php echo esc_html( __( 'MM', 'gravityforms' ) ); ?></label>
				</div>
				<?php
					if ( $this->timeFormat != '24' ) {
						?>
							<div class="gfield_time_ampm ginput_container">
								<select name="input_<?php echo esc_attr( $this->id ); ?>[]">
									<option value="am" <?php selected( 'am', $value['24'] ); ?>><?php echo esc_html( __( 'AM', 'gravityforms' ) ); ?></option>
									<option value="pm" <?php selected( 'pm', $value['24'] ); ?>><?php echo esc_html( __( 'PM', 'gravityforms' ) ); ?></option>
								</select>
							</div>
						<?php
					}
				?>
			</div>
			<?php
		}

		/**
		 * Unserialize the value into a workable array or arrays.
		 */
		private function unserialize( $value ) {
			$chunksize = $this->timeFormat == '24' ? 6 : 8;
			$values = array_chunk( maybe_unserialize( $value ), $chunksize );
			$value = array();
			foreach ( $values as $chunk ) {
				while ( count( $chunk ) < $chunksize ) {
					$chunk []= '';
				}
				$entry = array(
					'date' => array_shift( $chunk ),
					'checkin_hour' => array_shift( $chunk ), 'checkin_minute' => array_shift( $chunk ), 'checkin_24' => $this->timeFormat == '24' ? '' : array_shift( $chunk ),
					'checkout_hour' => array_shift( $chunk ), 'checkout_minute' => array_shift( $chunk ), 'checkout_24' => $this->timeFormat == '24' ? '' : array_shift( $chunk ),
					'comment' => array_shift( $chunk )
				);
				if ( !empty( $entry['date'] ) )
					$value []= $entry;
			}
			return $value;
		}

	}

	GF_Fields::register( new GF_Field_Timesheet() );
