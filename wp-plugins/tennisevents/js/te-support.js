/**
 * GrayWare Consulting
 * Support functions
 */
(function($) {
	$(document).ready (function() {
		// gw_support is required to continue, ensure the object exists
		if ( typeof gw_support === 'undefined' ) {
			console.log("*********GW_SUPPORT IS MISSING*********");
			return false;
		}
		console.log("GW SUPPORT:");
		console.log(gw_support);

		var prod_name = gw_support.var_prod_attr_names[0];
		console.log("Product Name: %s",prod_name);
		for(var i=1;i<gw_support.var_prod_attr_names.length; i++) {
			console.log("Attribute names: %s", gw_support.var_prod_attr_names[i]);
			var select_id = '#'.concat(gw_support.var_prod_attr_names[i]);
			$select = $(select_id);
			$select.on("click",{product_name: prod_name},function(e) {
				var sel = this.id;
	            var kids = $( e.target ).children();
	            kids.each(function(){
	            	if(this.selected && this.text == "Custom") {
	            		//console.log("Selected: %s for attribute %s and product: %s",this.text,sel,prod_name);
	            		var newloc = gw_support.redirect + '/?rts_prod=' + prod_name;
	            		//console.log("Redirect to: %s",newloc);
	            		window.location=newloc;
	            	}
	            });
	            e.preventDefault();
			});
		}
	});
})(jQuery);
