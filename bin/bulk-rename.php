<?php
// Rename files
function bulk_rename($folderPath) {
	//count total files in folder:
	$fi = new FilesystemIterator($folderPath, FilesystemIterator::SKIP_DOTS);
	printf("There were %d Files", iterator_count($fi));
	$digitCount = strlen((string) iterator_count($fi));
	//loop through files in folder
	if ($handler = opendir($folderPath)) {
		while ($file = readdir($handler)) {
			//find the integer in file names
			preg_match_all('!\d+!', $file, $matches);
			var_dump($matches);
			// add '0' into file name
			foreach ($matches as $match) {
				$number = array_pop($match);
				$i = $digitCount - strlen((string) $number);
				$new_number = $number;
				if ( $i ) {
					while ($i--)
						$new_number = '0' . $new_number;
				}

				// replace old integer in file name with new string
				$new_filename = str_replace($number, $new_number, $file);
				rename($folderPath.'/'.$file, $folderPath.'/'.$new_filename);
			}
		}
	}
}
