/*<![CDATA[*/
( function ( mw, $ ) {

function makeRequest( direction, id, time ) {
	// @todo FIXME: validate that direction is either 'Prev' or 'Next', no other values are OK
	$.ajax( {
		type: 'GET',
		url: mw.util.wikiScript(/* 'api' */),
		data: {
			action: 'ajax',
			rs: 'Intercom::get' + direction + 'Message',
			rsargs: [ id, time ]
			// format: 'json'
		}
	} ).done( function ( data ) {
		if ( $( '#intercommessage' ) && data ) {
			$( '#intercommessage' ).addClass( data.class ).text( data.message );
		}
	} ).fail( function ( jqXHR, textStatus, errorThrown ) {
		alert( 'An error occured:' + textStatus );
	} );
}

function nextMessage( id, time ) {
	makeRequest( 'Next', id, time );
}

function prevMessage( id, time ) {
	makeRequest( 'Prev', id, time );
}

function readNextMessage( id, time ) {
	$.ajax( {
		type: 'GET',
		url: mw.util.wikiScript(/* 'api' */),
		data: {
			action: 'ajax',
			rs: 'Intercom::getNextMessage',
			rsargs: [ id, time ]
			// format: 'json'
		}
	} ).done( function ( data ) {
		if ( data === 'false' /* sic! */ ) {
			$.ajax( {
				type: 'GET',
				url: mw.util.wikiScript(/* 'api' */),
				data: {
					action: 'ajax',
					rs: 'Intercom::getPrevMessage',
					rsargs: [ id, time ]
					// format: 'json'
				}
			} ).done( function ( data ) {
				if ( !$( '#intercommessage' ) ) {
					return;
				}
				if ( data === 'false' /* sic! */ ) {
					$( '#intercommessage' ).hide();
				} else {
					$( '#intercommessage' ).addClass( data.class ).text( data.message );
				}
			} ).fail( function ( jqXHR, textStatus, errorThrown ) {
				alert( 'An error occured:' + textStatus );
			} );
		}
	} ).fail( function ( jqXHR, textStatus, errorThrown ) {
		alert( 'An error occured:' + textStatus );
	} );
}

function markRead( id, time ) {
	$.ajax( {
		type: 'GET',
		url: mw.util.wikiScript(/* 'api' */),
		data: {
			action: 'ajax',
			rs: 'Intercom::markRead',
			rsargs: [ id ]
			// format: 'json'
		}
	} ).done( function ( data ) {
		if ( data === 'true' /* sic! */ ) {
			readNextMessage( id, time );
		}
	} ).fail( function ( jqXHR, textStatus, errorThrown ) {
		alert( 'An error occured:' + textStatus );
	} );
}

// Handlers for the links/buttons in the site notice area when an intercom
// message is active
// @todo FIXME: Doesn't seem to work, at least w/ the DismissableSiteNotice ext. enabled
$( function () {
	// Next
	$( 'body' ).on( 'click', '#intercommessage .intercom-button-next', function ( e ) {
		e.preventDefault(); // don't follow the hash
		nextMessage(
			$( this ).data( 'intercom-id' ),
			$( this ).data( 'intercom-message-time' )
		);
	} );

	// Previous
	$( 'body' ).on( 'click', '#intercommessage .intercom-button-previous', function ( e ) {
		e.preventDefault(); // don't follow the hash
		prevMessage(
			$( this ).data( 'intercom-id' ),
			$( this ).data( 'intercom-message-time' )
		);
	} );

	// Mark as read
	$( 'body' ).on( 'click', '#intercommessage .intercom-button-markasread', function ( e ) {
		e.preventDefault(); // don't follow the hash
		markRead(
			$( this ).data( 'intercom-id' ),
			$( this ).data( 'intercom-message-time' )
		);
	} );
} );

}( mediaWiki, jQuery ) );
/*]]>*/
