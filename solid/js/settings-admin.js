$(document).ready(function() {

	$('#solid-private-key').change(function(el) {
		OCP.AppConfig.setValue('solid','privateKey',this.value);
	});

	$('#solid-public-key').change(function(el) {
		OCP.AppConfig.setValue('solid','publicKey',this.value);
	});

});