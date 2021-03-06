jLoader.Initialize();

// ---[ ]---------------------------------------------------------------------------
jLoader.Document = function ( ) {

	jTranslations = new Array ();
	
	globalTheme = 'default';
	
	jLoader.Document.RewriteBrokenIcons ( );
	
	$("#form-elements").validate();
	
}

jLoader.Document.RewriteBrokenIcons = function ( ) {

	noIconLocation = '/themes/' + globalTheme + '/images/noicon.gif';
	
	$('.usericon').preload({
	    placeholder:'placeholder.jpg',
    	notFound:noIconLocation
	});

}

jLoader.Document.Select = function ( pElement, pParent ) { }

jLoader.Document.Select.OnChange = function ( pElement, pParent ) { 

	if ( pElement.name == 'PaginationStep' ) {
		var steps = $("[name='PaginationStep']");
		for ( s = 0; s < steps.length; s++ ) {
			steps.val ( pElement.value );
		}
		pElement.form.submit();
	}
	
}


jLoader.Document.Anchor = function ( pElement, pParent ) { }

jLoader.Document.Anchor.OnClick = function ( pElement, pParent ) { 

	if ( pElement.className == 'previous-link' ) {
		window.history.back(-1);
		return ( false );
	}	
	
}


function __ ( pText, pData ) {
	
    var parentElement = arguments.callee.caller.arguments[0];
    
    var translation =  jTranslations[parentElement.id];
    
    if ( typeof translation == 'undefined' ) {
    	// @todo Build an error logging system
    	alert ( "Translation Element Undefined For " + parentElement.id );
    	return ( false );
    }
    
    var translationElementId = 'appleseed-language-components-' + translation;
    
    var translationElement = document.getElementById ( translationElementId );
    
    // @todo:  Load global data first, then overwrite with local data.
    
    translationData = JSON.parse(translationElement.innerText);
    
	var text = sprintfn ( pText, pData );
	return ( text );
}

function sprintfn ( pText, pData ) {

	key = pText.toUpperCase();
	key = key.replace ( / /g, '_' );
	
	jskey = 'JS_' + key;
	
	text = translationData[key];
	
	if (typeof translationData[key] != 'undefined') {
		text = translationData[key];
	} else if ( typeof translationData[jskey] != 'undefined' ) {
		text = translationData[jskey];
	} else {
		return ( pText );
	}
	
	for ( key in pData ) {
		var pattern = new RegExp ( "\\%" + key + "\\$s", "g" );
		text = text.replace( pattern, pData[key]);
	}
	
	return ( text );
}
