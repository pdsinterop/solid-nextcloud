// SPDX-FileCopyrightText: 2020, Michiel de Jong <<michiel@unhosted.org>>
// SPDX-License-Identifier: MIT






$(document).ready(function() {

	$('#solid-private-key').change(function(el) {
		OCP.AppConfig.setValue('solid','privateKey',this.value);
	});

	$('#solid-encryption-key').change(function(el) {
		OCP.AppConfig.setValue('solid','encryptionKey',this.value);
	});

});
