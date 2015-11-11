<?php
require_once 'google-api-php-client/src/Google/Client.php';
require_once 'google-api-php-client/src/Google/Service/Oauth2.php';
require_once 'google-api-php-client/src/Google/Service/Drive.php';
session_start();

header('Content-Type: text/html; charset=utf-8');

// Init the variables
$driveInfo = "";
$folderName = "";
$folderDesc = "";

// Get the file path from the variable
$file_tmp_name = $_FILES["file"]["tmp_name"];

// Get the client Google credentials
$credentials = $_COOKIE["credentials"];

// Get your app info from JSON downloaded from google dev console
$json = json_decode(file_get_contents("client_secret.json"), true);
$CLIENT_ID = $json['installed']['client_id'];
$CLIENT_SECRET = $json['installed']['client_secret'];
$REDIRECT_URI = $json['installed']['redirect_uris'][1];
//$REDIRECT_URI='formAction.php';

// Create a new Client
$client = new Google_Client();
$client->setClientId($CLIENT_ID);
$client->setClientSecret($CLIENT_SECRET);
$client->setRedirectUri($REDIRECT_URI);
$client->addScope(
	"https://www.googleapis.com/auth/drive", 
	"https://www.googleapis.com/auth/drive.appfolder");

// Refresh the user token and grand the privileges
$client->setAccessToken($credentials);
$service = new Google_Service_Drive($client);

// Set the file metadata for drive
$mimeType = $_FILES["file"]["type"];
$title = $_FILES["file"]["name"];
$description = "Uploaded from your very first google drive application!";

// Get the folder metadata
if (!empty($_POST["folderName"]))
	$folderName = $_POST["folderName"];
if (!empty($_POST["folderDesc"]))
	$folderDesc = $_POST["folderDesc"];

// Call the insert function with parameters listed below
$driveInfo = insertFile($service, $title, $description, $mimeType, $file_tmp_name, $folderName, $folderDesc);

/**
* Get the folder ID if it exists, if it doesnt exist, create it and return the ID
*
* @param Google_DriveService $service Drive API service instance.
* @param String $folderName Name of the folder you want to search or create
* @param String $folderDesc Description metadata for Drive about the folder (optional)
* @return Google_Drivefile that was created or got. Returns NULL if an API error occured
*/
function getFolderExistsCreate($service, $folderName, $folderDesc) {
	// List all user files (and folders) at Drive root
	$files = $service->files->listFiles();
	$found = false;

	// Go through each one to see if there is already a folder with the specified name
	foreach ($files['items'] as $item) {
		if ($item['title'] == $folderName) {
			$found = true;
			return $item['id'];
			break;
		}
	}

	// If not, create one
	if ($found == false) {
		$folder = new Google_Service_Drive_DriveFile();

		//Setup the folder to create
		$folder->setTitle($folderName);

		if(!empty($folderDesc))
			$folder->setDescription($folderDesc);

		$folder->setMimeType('application/vnd.google-apps.folder');

		//Create the Folder
		try {
			$createdFile = $service->files->insert($folder, array(
				'mimeType' => 'application/vnd.google-apps.folder',
				));

			// Return the created folder's id
			return $createdFile->id;
		} catch (Exception $e) {
			print "An error occurred: " . $e->getMessage();
		}
	}
}

/**
 * Insert a new permission.
 *
 * @param Google_Service_Drive $service Drive API service instance.
 * @param String $fileId ID of the file to insert permission for.
 * @param String $value User or group e-mail address, domain name or NULL for
 *                     "default" type.
 * @param String $type The value "user", "group", "domain" or "default".
 * @param String $role The value "owner", "writer" or "reader".
 * @return Google_Servie_Drive_Permission The inserted permission. NULL is
 *     returned if an API error occurred.
 */
function insertPermission($service, $fileId, $value, $type, $role) {
  $newPermission = new Google_Service_Drive_Permission();
  $newPermission->setValue($value);
  $newPermission->setType($type);
  $newPermission->setRole($role);
  try {
    return $service->permissions->insert($fileId, $newPermission);
  } catch (Exception $e) {
    print "An error occurred: " . $e->getMessage();
  }
  return NULL;
}


/**
 * Insert new file in the Application Data folder.
 *
 * @param Google_DriveService $service Drive API service instance.
 * @param string $title Title of the file to insert, including the extension.
 * @param string $description Description of the file to insert.
 * @param string $mimeType MIME type of the file to insert.
 * @param string $filename Filename of the file to insert.
 * @return Google_DriveFile The file that was inserted. NULL is returned if an API error occurred.
 */
function insertFile($service, $title, $description, $mimeType, $filename, $folderName, $folderDesc) {
	$file = new Google_Service_Drive_DriveFile();

	$new_mime_type = 'application/vnd.google-apps.document';

	// Set the metadata
	$file->setTitle($title);
	$file->setDescription($description);
	$file->setMimeType($new_mime_type);

	// Setup the folder you want the file in, if it is wanted in a folder
	if(isset($folderName)) {
		if(!empty($folderName)) {
			$parent = new Google_Service_Drive_ParentReference();
			$parent->setId(getFolderExistsCreate($service, $folderName, $folderDesc));
			$file->setParents(array($parent));
		}
	}
	try {
		// Get the contents of the file uploaded
		$data = file_get_contents($filename);

		// Try to upload the file, you can add the parameters e.g. if you want to convert a .doc to editable google format, add 'convert' = 'true'
		$createdFile = $service->files->insert($file, array(
			'data' => $data,
			'mimeType' => $mimeType,
			'uploadType'=> 'multipart',
			'convert' => 'true'
			));

	

		// Return a bunch of data including the link to the file we just uploaded
		return $createdFile;
	} catch (Exception $e) {
		print "An error occurred: " . $e->getMessage();
	}
}


$permission_data = insertPermission($service, $driveInfo["id"], 'amila128@gmail.com', 'anyone', 'reader');
echo "<br>Link to file: " . $driveInfo["alternateLink"];
header('location:'.$driveInfo["alternateLink"]);
?>