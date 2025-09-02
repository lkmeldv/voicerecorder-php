<?php
// Bloquer l'accès direct au dossier uploads
http_response_code(403);
exit('Accès interdit');
?>