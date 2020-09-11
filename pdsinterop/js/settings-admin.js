$(document).ready(function() {

	$('#pdsinterop-private-key').change(function(el) {
		OCP.AppConfig.setValue('pdsinterop','privateKey',this.value);
	});

	$('#pdsinterop-public-key').change(function(el) {
		OCP.AppConfig.setValue('pdsinterop','publicKey',this.value);
	});

});