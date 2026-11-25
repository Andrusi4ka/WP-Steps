/**
 * WP Steps "What's new" window (class-wp-steps-whats-new.php), as WP Banner's: opens by
 * itself on the plugin's screens, from the notice on the Dashboard. Closing
 * it, or dismissing the notice, marks the version as seen for this user.
 */
( function ( $ ) {
	'use strict';

	var settings = window.wpstWhatsNew || {};

	$( function () {
		var $modal = $( '.wpst-news' ).appendTo( document.body );
		var $notice = $( '.wpst-news-notice' );
		var opener = null;
		var told = false;

		if ( ! $modal.length ) {
			return;
		}

		// Once: the server remembers it for this user.
		function seen() {
			if ( told ) {
				return;
			}
			told = true;
			$.post( settings.ajaxUrl, { action: settings.action, _ajax_nonce: settings.nonce } );
		}

		function open() {
			opener = document.activeElement;
			$modal.prop( 'hidden', false );
			$( document.body ).addClass( 'wpst-news-open' );
			$modal.find( '.wpst-news__done' ).trigger( 'focus' );
		}

		function close() {
			$modal.prop( 'hidden', true );
			$( document.body ).removeClass( 'wpst-news-open' );
			$notice.remove();
			seen();
			if ( opener && opener.focus && document.body.contains( opener ) ) {
				opener.focus();
			}
		}

		$modal.on( 'click', '.wpst-news__done, .wpst-news__close, .wpst-news__backdrop', close );
		$( document ).on( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && ! $modal.prop( 'hidden' ) ) {
				close();
			}
		} );

		$notice.on( 'click', '.wpst-news-open', open );
		// The notice's own close button (WordPress adds it to .is-dismissible).
		$notice.on( 'click', '.notice-dismiss', seen );

		if ( settings.autoOpen ) {
			open();
		}
	} );
}( jQuery ) );
