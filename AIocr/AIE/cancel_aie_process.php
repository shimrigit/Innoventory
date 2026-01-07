<?php
/**
 * Cancel AIE Processing - Clear session and stop
 */

session_start();

// Clear all AIE session variables
unset($_SESSION['aie_crop_files']);
unset($_SESSION['aie_crop_names']);
unset($_SESSION['aie_pdf_basename']);
unset($_SESSION['aie_mode']);
unset($_SESSION['aie_ocr_engine']);
unset($_SESSION['aie_pdf_file']);

// Return success
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Processing cancelled']);
exit;
