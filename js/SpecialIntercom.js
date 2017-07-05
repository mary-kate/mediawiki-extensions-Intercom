( function ( mw, $ ) {
	$( function () {
		$( '#wpExpiry' ).change( function () {
			var expiryDrop = document.getElementById( 'wpExpiry' );
			var expiryOther = document.getElementById( 'wpExpiryOther' );
			if ( expiryDrop && expiryOther ) {
				if ( expiryDrop.value == 'other' ) {
					expiryOther.style.display = 'inline';
				} else {
					expiryOther.style.display = 'none';
				}
			}
		} );
	} );
}( mediaWiki, jQuery ) );