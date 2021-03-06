<?php
/*
	services_ftp.php

	Part of NAS4Free (http://www.nas4free.org).
	Copyright (c) 2012-2017 The NAS4Free Project <info@nas4free.org>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice, this
	   list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright notice,
	   this list of conditions and the following disclaimer in the documentation
	   and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
	DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
	ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
	(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
	ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
	(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
	SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

	The views and conclusions contained in the software and documentation are those
	of the authors and should not be interpreted as representing official policies,
	either expressed or implied, of the NAS4Free Project.
*/
require 'auth.inc';
require 'guiconfig.inc';

$l_sysloglevel = [
	'emerg' => gtext('Emergency Level'),
	'alert' => gtext('Alert Level'),
	'crit' => gtext('Critical Error Level'),
	'error' => gtext('Error Level'),
	'warn' => gtext('Warning Level'),
	'notice' => gtext('Notice Level'),
	'info' => gtext('Info Level'),
	'debug' => gtext('Debug Level')
];
array_make_branch($config,'ftpd');
$pconfig['enable'] = isset($config['ftpd']['enable']);
$pconfig['port'] = $config['ftpd']['port'];
$pconfig['numberclients'] = $config['ftpd']['numberclients'];
$pconfig['maxconperip'] = $config['ftpd']['maxconperip'];
$pconfig['maxloginattempts'] = $config['ftpd']['maxloginattempts'];
$pconfig['timeout'] = $config['ftpd']['timeout'];
$pconfig['anonymousonly'] = isset($config['ftpd']['anonymousonly']);
$pconfig['localusersonly'] = isset($config['ftpd']['localusersonly']);
$pconfig['allowgroup'] = !empty($config['ftpd']['allowgroup']) ? $config['ftpd']['allowgroup'] : "";
$pconfig['pasv_max_port'] = $config['ftpd']['pasv_max_port'];
$pconfig['pasv_min_port'] = $config['ftpd']['pasv_min_port'];
$pconfig['pasv_address'] = $config['ftpd']['pasv_address'];
$pconfig['userbandwidthup'] = $config['ftpd']['userbandwidth']['up'];
$pconfig['userbandwidthdown'] = $config['ftpd']['userbandwidth']['down'];
$pconfig['anonymousbandwidthup'] = $config['ftpd']['anonymousbandwidth']['up'];
$pconfig['anonymousbandwidthdown'] = $config['ftpd']['anonymousbandwidth']['down'];
if (!empty($config['ftpd']['filemask'])) {
	$pconfig['filemask'] = $config['ftpd']['filemask'];
} else {
	$pconfig['filemask'] = "077";
}
if (!empty($config['ftpd']['directorymask'])) {
	$pconfig['directorymask'] = $config['ftpd']['directorymask'];
} else {
	$pconfig['directorymask'] = "022";
}
$pconfig['banner'] = $config['ftpd']['banner'];
$pconfig['fxp'] = isset($config['ftpd']['fxp']);
$pconfig['allowrestart'] = isset($config['ftpd']['allowrestart']);
$pconfig['permitrootlogin'] = isset($config['ftpd']['permitrootlogin']);
$pconfig['chrooteveryone'] = isset($config['ftpd']['chrooteveryone']);
$pconfig['identlookups'] = isset($config['ftpd']['identlookups']);
$pconfig['usereversedns'] = isset($config['ftpd']['usereversedns']);
$pconfig['disabletcpwrapper'] = isset($config['ftpd']['disabletcpwrapper']);
$pconfig['tls'] = isset($config['ftpd']['tls']);
$pconfig['tlsrequired'] = isset($config['ftpd']['tlsrequired']);
$pconfig['privatekey'] = base64_decode($config['ftpd']['privatekey']);
$pconfig['certificate'] = base64_decode($config['ftpd']['certificate']);
$pconfig['sysloglevel'] = isset($config['ftpd']['sysloglevel']) ? $config['ftpd']['sysloglevel'] : 'notice';
if(isset($config['ftpd']['auxparam']) && is_array($config['ftpd']['auxparam'])):
	$pconfig['auxparam'] = implode("\n", $config['ftpd']['auxparam']);
endif;

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (isset($_POST['enable']) && $_POST['enable']) {
		// Input validation.
		$reqdfields = ['port','numberclients','maxconperip','maxloginattempts','timeout'];
		$reqdfieldsn = [gtext('TCP Port'),gtext('Max. Clients'),gtext('Max. Connections Per Host'),gtext('Max. Login Attempts'),gtext('Timeout')];
		$reqdfieldst = ['numeric','numeric','numeric','numeric','numeric'];

		if (!empty($_POST['tls'])) {
			$reqdfields = array_merge($reqdfields, ['certificate','privatekey']);
			$reqdfieldsn = array_merge($reqdfieldsn, [gtext('Certificate'),gtext('Private key')]);
			$reqdfieldst = array_merge($reqdfieldst, ['certificate','privatekey']);
		}

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
		do_input_validation_type($_POST, $reqdfields, $reqdfieldsn, $reqdfieldst, $input_errors);

		if (!is_port($_POST['port'])) {
			$input_errors[] = gtext('The TCP Port must be a valid port number.');
		}

		if ((1 > $_POST['numberclients']) || (50 < $_POST['numberclients'])) {
			$input_errors[] = gtext('The max. number of clients must be in the range from 1 to 50.');
		}

		if (0 > $_POST['maxconperip']) {
			$input_errors[] = gtext('The max. connections per IP address must be either 0 (unlimited) or greater.');
		}

		if (!is_numericint($_POST['timeout'])) {
			$input_errors[] = gtext('The maximum idle time must be a number.');
		}

		if (("0" !== $_POST['pasv_min_port']) && (($_POST['pasv_min_port'] < 1024) || ($_POST['pasv_min_port'] > 65535))) {
			$input_errors[] = sprintf(gtext("The attribute '%s' must be in the range from %d to %d."), gtext("Min. Passive Port"), 1024, 65535);
		}

		if (("0" !== $_POST['pasv_max_port']) && (($_POST['pasv_max_port'] < 1024) || ($_POST['pasv_max_port'] > 65535))) {
			$input_errors[] = sprintf(gtext("The attribute '%s' must be in the range from %d to %d."), gtext("Max. Passive Port"), 1024, 65535);
		}

		if (("0" !== $_POST['pasv_min_port']) && ("0" !== $_POST['pasv_max_port'])) {
			if ($_POST['pasv_min_port'] >= $_POST['pasv_max_port']) {
				$input_errors[] = sprintf(gtext("The attribute '%s' must be less than '%s'."), gtext("Min. Passive Port"), gtext("Max. Passive Port"));
			}
		}

		if (!empty($_POST['anonymousonly']) && !empty($_POST['localusersonly'])) {
			$input_errors[] = gtext("It is impossible to enable 'Anonymous Users' and 'Authenticated Users' both at the same time.");
		}
	}

	if (empty($input_errors)) {
		$config['ftpd']['enable'] = isset($_POST['enable']) ? true : false;
		$config['ftpd']['numberclients'] = $_POST['numberclients'];
		$config['ftpd']['maxconperip'] = $_POST['maxconperip'];
		$config['ftpd']['maxloginattempts'] = $_POST['maxloginattempts'];
		$config['ftpd']['timeout'] = $_POST['timeout'];
		$config['ftpd']['port'] = $_POST['port'];
		$config['ftpd']['anonymousonly'] = isset($_POST['anonymousonly']) ? true : false;
		$config['ftpd']['localusersonly'] = isset($_POST['localusersonly']) ? true : false;
		$config['ftpd']['allowgroup'] = $_POST['allowgroup'];
		$config['ftpd']['pasv_max_port'] = $_POST['pasv_max_port'];
		$config['ftpd']['pasv_min_port'] = $_POST['pasv_min_port'];
		$config['ftpd']['pasv_address'] = $_POST['pasv_address'];
		$config['ftpd']['banner'] = $_POST['banner'];
		$config['ftpd']['filemask'] = $_POST['filemask'];
		$config['ftpd']['directorymask'] = $_POST['directorymask'];
		$config['ftpd']['fxp'] = isset($_POST['fxp']) ? true : false;
		$config['ftpd']['allowrestart'] = isset($_POST['allowrestart']) ? true : false;
		$config['ftpd']['permitrootlogin'] = isset($_POST['permitrootlogin']) ? true : false;
		$config['ftpd']['chrooteveryone'] = isset($_POST['chrooteveryone']) ? true : false;
		$config['ftpd']['identlookups'] = isset($_POST['identlookups']) ? true : false;
		$config['ftpd']['usereversedns'] = isset($_POST['usereversedns']) ? true : false;
		$config['ftpd']['disabletcpwrapper'] = isset($_POST['disabletcpwrapper']) ? true : false;
		$config['ftpd']['tls'] = isset($_POST['tls']) ? true : false;
		$config['ftpd']['tlsrequired'] = isset($_POST['tlsrequired']) ? true : false;
		$config['ftpd']['privatekey'] = base64_encode($_POST['privatekey']);
		$config['ftpd']['certificate'] = base64_encode($_POST['certificate']);
		$config['ftpd']['userbandwidth']['up'] = $pconfig['userbandwidthup'];
		$config['ftpd']['userbandwidth']['down'] = $pconfig['userbandwidthdown'];
		$config['ftpd']['anonymousbandwidth']['up'] = $pconfig['anonymousbandwidthup'];
		$config['ftpd']['anonymousbandwidth']['down'] = $pconfig['anonymousbandwidthdown'];
		$config['ftpd']['sysloglevel'] = $pconfig['sysloglevel'];

		# Write additional parameters.
		unset($config['ftpd']['auxparam']);
		foreach (explode("\n", $_POST['auxparam']) as $auxparam) {
			$auxparam = trim($auxparam, "\t\n\r");
			if (!empty($auxparam))
				$config['ftpd']['auxparam'][] = $auxparam;
		}

		write_config();

		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval |= rc_update_service("proftpd");
			$retval |= rc_update_service("mdnsresponder");
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
	}
}
$pgtitle = [gtext('Services'),gtext('FTP')];
?>
<?php include 'fbegin.inc';?>
<script type="text/javascript">
<!--
function enable_change(enable_change) {
	var endis = !(document.iform.enable.checked || enable_change);
	document.iform.port.disabled = endis;
	document.iform.timeout.disabled = endis;
	document.iform.permitrootlogin.disabled = endis;
	document.iform.numberclients.disabled = endis;
	document.iform.maxconperip.disabled = endis;
	document.iform.maxloginattempts.disabled = endis;
	document.iform.anonymousonly.disabled = endis;
	document.iform.localusersonly.disabled = endis;
	document.iform.allowgroup.disabled = endis;
	document.iform.banner.disabled = endis;
	document.iform.fxp.disabled = endis;
	document.iform.allowrestart.disabled = endis;
	document.iform.pasv_max_port.disabled = endis;
	document.iform.pasv_min_port.disabled = endis;
	document.iform.pasv_address.disabled = endis;
	document.iform.filemask.disabled = endis;
	document.iform.directorymask.disabled = endis;
	document.iform.chrooteveryone.disabled = endis;
	document.iform.identlookups.disabled = endis;
	document.iform.usereversedns.disabled = endis;
	document.iform.disabletcpwrapper.disabled = endis;
	document.iform.tls.disabled = endis;
	document.iform.tlsrequired.disabled = endis;
	document.iform.privatekey.disabled = endis;
	document.iform.certificate.disabled = endis;
	document.iform.userbandwidthup.disabled = endis;
	document.iform.userbandwidthdown.disabled = endis;
	document.iform.anonymousbandwidthup.disabled = endis;
	document.iform.anonymousbandwidthdown.disabled = endis;
	document.iform.sysloglevel.disabled = endis;
	document.iform.auxparam.disabled = endis;
}

function tls_change() {
	switch (document.iform.tls.checked) {
		case true:
			showElementById('tlsrequired_tr','show');
			showElementById('privatekey_tr','show');
			showElementById('certificate_tr','show');
			break;

		case false:
			showElementById('tlsrequired_tr','hide');
			showElementById('privatekey_tr','hide');
			showElementById('certificate_tr','hide');
			break;
	}
}

function localusersonly_change() {
	switch (document.iform.localusersonly.checked) {
		case true:
			showElementById('allowgroup_tr','show');
			showElementById('anonymousbandwidthup_tr','hide');
			showElementById('anonymousbandwidthdown_tr','hide');
			break;

		case false:
			showElementById('allowgroup_tr','hide');
			showElementById('anonymousbandwidthup_tr','show');
			showElementById('anonymousbandwidthdown_tr','show');
			break;
	}
}

function anonymousonly_change() {
	switch (document.iform.anonymousonly.checked) {
		case true:
			showElementById('userbandwidthup_tr','hide');
			showElementById('userbandwidthdown_tr','hide');
			break;

		case false:
			showElementById('userbandwidthup_tr','show');
			showElementById('userbandwidthdown_tr','show');
			break;
	}
}
//-->
</script>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td class="tabnavtbl">
			<ul id="tabnav">
				<li class="tabact"><a href="services_ftp.php" title="<?=gtext('Reload page');?>"><span><?=gtext("Settings");?></span></a></li>
				<li class="tabinact"><a href="services_ftp_mod.php"><span><?=gtext("Modules");?></span></a></li>
			</ul>
		</td>
	</tr>
	<tr>
		<td class="tabcont">
			<form action="services_ftp.php" method="post" name="iform" id="iform" onsubmit="spinner()">
				<?php
				if (!empty($input_errors)) {
					print_input_errors($input_errors);
				}
				if (!empty($savemsg)) {
					print_info_box($savemsg);
				}
				?>
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
					<?php 
					html_titleline_checkbox("enable", gtext("File Transfer Protocol"), !empty($pconfig['enable']) ? true : false, gtext("Enable"), "enable_change(false)");
					html_inputbox("port", gtext("TCP Port"), $pconfig['port'], sprintf(gtext("Enter a custom port number if you want to override the default port. (Default is %s)."), "21"), true, 4);
					html_inputbox("numberclients", gtext("Max. Clients"), $pconfig['numberclients'], gtext("Maximum number of users that can connect to the server."), true, 3);
					html_inputbox("maxconperip", gtext("Max. Connections Per Host"), $pconfig['maxconperip'], gtext("Maximum number of connections allowed per IP address. (0 = unlimited)."), true, 3);
					html_inputbox("maxloginattempts", gtext("Max. Login Attempts"), $pconfig['maxloginattempts'], gtext("Maximum number of allowed password attempts before disconnection."), true, 3);
					html_inputbox("timeout", gtext("Timeout"), $pconfig['timeout'], gtext("Maximum idle time in seconds."), true, 5);
					html_checkbox("permitrootlogin", gtext("Permit Root Login"), !empty($pconfig['permitrootlogin']) ? true : false, gtext("Specifies whether it is allowed to login as superuser (root) directly."), "", false);
					html_checkbox("anonymousonly", gtext("Anonymous Users"), !empty($pconfig['anonymousonly']) ? true : false, gtext("Only allow anonymous users. Use this on a public FTP site with no remote FTP access to real accounts."), "", false, "anonymousonly_change()");
					html_checkbox("localusersonly", gtext("Authenticated Users"), !empty($pconfig['localusersonly']) ? true : false, gtext("Only allow authenticated users. Anonymous logins are prohibited."), "", false, "localusersonly_change()");
					html_inputbox("allowgroup", gtext("Group Allow"), $pconfig['allowgroup'], gtext("Comma-separated list of group names that are permitted to login to the FTP server. (empty = ftp group)."), false, 40);
					html_textarea("banner", gtext("Banner"), $pconfig['banner'], gtext("Greeting banner displayed to client when firts connection comes in."), false, 65, 7, false, false);
					html_separator();
					html_titleline(gtext("Advanced Settings"));
					html_inputbox("filemask", gtext("File Mask"), $pconfig['filemask'], gtext("Use this option to override the file creation mask (077 by default)."), false, 3);
					html_inputbox("directorymask", gtext("Directory Mask"), $pconfig['directorymask'], gtext("Use this option to override the directory creation mask (022 by default)."), false, 3);
					html_checkbox("fxp", gtext("FXP"), !empty($pconfig['fxp']) ? true : false, gtext("Enable FXP protocol."), gtext("FXP allows transfers between two remote servers without any file data going to the client asking for the transfer (insecure!)."), false);
					html_checkbox("allowrestart", gtext("Resume"), !empty($pconfig['allowrestart']) ? true : false, gtext("Allow clients to resume interrupted uploads and downloads."), "", false);
					html_checkbox("chrooteveryone", gtext("Default Root"), !empty($pconfig['chrooteveryone']) ? true : false, gtext("chroot() everyone, but root."), gtext("If default root is enabled, a chroot operation is performed immediately after a client authenticates. This can be used to effectively isolate the client from a portion of the host system filespace."), false);
					html_checkbox("identlookups", gtext("IdentLookups"), !empty($pconfig['identlookups']) ? true : false, gtext("Enable the ident protocol (RFC1413)."), gtext("When a client initially connects to the server the ident protocol is used to attempt to identify the remote username."), false);
					html_checkbox("usereversedns", gtext("Reverse DNS lookup"), !empty($pconfig['usereversedns']) ? true : false, gtext("Enable reverse DNS lookup."), gtext("Enable reverse DNS lookup performed on the remote host's IP address for incoming active mode data connections and outgoing passive mode data connections."), false);
					html_checkbox("disabletcpwrapper", gtext("TCP Wrapper"), !empty($pconfig['disabletcpwrapper']) ? true : false, gtext("Disable TCP wrapper (mod_wrap module)."), "", false);
					html_inputbox("pasv_address", gtext("Masquerade Address"), $pconfig['pasv_address'], gtext("Causes the server to display the network information for the specified IP address or DNS hostname to the client, on the assumption that that IP address or DNS host is acting as a NAT gateway or port forwarder for the server."), false, 20);
					html_inputbox("pasv_min_port", gtext("Passive Port"), $pconfig['pasv_min_port'], gtext("The minimum port to allocate for PASV style data connections (0 = use any port)."), false, 20);
					html_inputbox("pasv_max_port", "&nbsp;", $pconfig['pasv_max_port'], gtext("The maximum port to allocate for PASV style data connections (0 = use any port).") . "<br /><br />" . gtext("Passive ports restricts the range of ports from which the server will select when sent the PASV command from a client. The server will randomly choose a number from within the specified range until an open port is found. The port range selected must be in the non-privileged range (eg. greater than or equal to 1024). It is strongly recommended that the chosen range be large enough to handle many simultaneous passive connections (for example, 49152-65534, the IANA-registered ephemeral port range)."), true, 20);
					html_inputbox("userbandwidthup", gtext("Transfer Rate"), $pconfig['userbandwidthup'], gtext("Authenticated user upload bandwith in KB/s. An empty field means infinity."), false, 5);
					html_inputbox("userbandwidthdown", "&nbsp;", $pconfig['userbandwidthdown'], gtext("Authenticated user download bandwith in KB/s. An empty field means infinity."), false, 5);
					html_inputbox("anonymousbandwidthup", gtext("Transfer Rate"), $pconfig['anonymousbandwidthup'], gtext("Anonymous user upload bandwith in KB/s. An empty field means infinity."), false, 5);
					html_inputbox("anonymousbandwidthdown", "&nbsp;", $pconfig['anonymousbandwidthdown'], gtext("Anonymous user download bandwith in KB/s. An empty field means infinity."), false, 5);
					html_checkbox("tls", gtext("TLS"), !empty($pconfig['tls']) ? true : false, gtext("Enable TLS connections."), "", false, "tls_change()");
					html_textarea("certificate", gtext("Certificate"), $pconfig['certificate'], gtext("Paste a signed certificate in X.509 PEM format here."), true, 65, 7, false, false);
					html_textarea("privatekey", gtext("Private Key"), $pconfig['privatekey'], gtext("Paste an private key in PEM format here."), true, 65, 7, false, false);
					html_checkbox("tlsrequired", gtext("TLS Only"), !empty($pconfig['tlsrequired']) ? true : false, gtext("Allow TLS connections only."), "", false);
					html_combobox('sysloglevel', gtext('Syslog Level'), $pconfig['sysloglevel'], $l_sysloglevel, '');
					$helpinghand = '<a href="'
						. 'http://www.proftpd.org/docs/directives/linked/configuration.html'
						. '" target="_blank">'
						. gtext('Please check the documentation')
						. '</a>.';
					html_textarea("auxparam", gtext("Additional Parameters"), !empty($pconfig['auxparam']) ? $pconfig['auxparam'] : "", sprintf(gtext("These parameters are added to %s."), "proftpd.conf") . " " . $helpinghand, false, 65, 5, false, false);
					?>
				</table>
				<div id="submit">
					<input name="Submit" type="submit" class="formbtn" value="<?=gtext("Save & Restart");?>" onclick="enable_change(true)" />
				</div>
				<?php include 'formend.inc';?>
			</form>
		</td>
	</tr>
</table>
<script type="text/javascript">
<!--
enable_change(false);
anonymousonly_change();
localusersonly_change();
tls_change();
//-->
</script>
<?php include 'fend.inc';?>
