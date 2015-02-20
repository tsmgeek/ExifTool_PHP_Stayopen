ExifTool_PHP_Stayopen
=====================

Ive created a script that can be used to run ExifTool in StayOpen mode within PHP.
The script can be inited as a singleton or instance.
Ive also supplied two additional scripts that wrap the class ready for use with gearman for scaleout.
The class does not apply any logic to the parameters supplied to ExifTool, what you push though gets passed though and the result is then returned.
The class detects if ExifTool has died and restarts it on the next call.

It still has some work to do and cleaning up but seems to work well in my environment and scales out quite nicely.
Originally I was using PHP streams but this proved to be a problem so instead using fgets to parse the return.
Note that script is hard coded to work with 9.03+ of ExifTool only because this is all I have been testing against since.

Let me know what you think or any changes to make it better.

Phil Harvey's Exiftool  - http://www.sno.phy.queensu.ca/~phil/exiftool/

Phil Harvey's Exiftool  (Forum Dev Post) - http://u88.n24.queensu.ca/exiftool/forum/index.php/topic,5381.0.html

Performance tests
-
These figures are to be taken as a guide of performance increase possible with supplied scripts but will vary depending on your hardware setup and arguments supplied to ExifTool.

100 iterations fetching metadata from a JPEG (-use MWG -g -j -all:all)<br/>
1 GM Instance - 1.3s<br/>
2 GM Instances - 0.9s<br/>
3 GM Instances - 0.7s<br/>
4 GM Instances - 0.6s<br/>

300 iterations with 4 GM Instances - 1.6s<br/>
100 iterations 3 files on each iteration with 4 GM Instances - 1.4s<br/>
50 iterations 6 files on each iteration with 4 GM Instances - 1.1s<br/>
100 iterations 10 files on each iteration with 4 GM Instances - 3.1s<br/>

Usage
-
Below is a basic example on how to use this class.
Put all your commands in an array and push it into the stack using the `$exif->add()` function, you can add multiple jobs to process before calling `fetch()`/`fetchAll()`.

`getInstance()` setup class as a singleton<br/>
`setExifToolPath($path)` set/change the path of ExifTool if not supplied at start<br/>
`close()` terminate ExifTool background process<br/>
`start()` start ExifTool background process<br/>
`test()` to check if ExifTool is running.<br/>
`clear()` clear the stack<br/>
`fetch()` will return one processed item off the stack at a time.<br/>
`fetchAll()` will return a single array with all items in the stack processed.<br/>
`getError($id)` will return FALSE or STDERR output if there was an error, pass index if `fetchAll()` was used<br/>
`getErrorStr($id)` will return STDERR output, pass index if `fetchAll()` was used<br/>
`getSummary(msg)` will return summary value from msg passed which are defined as const's<br/>

There are also calls to `fetchDecoded()`/`fetchAllDecoded()` which essentialy will decode the output in one step if your default arguments contains '-j' JSON output, the default for the script is ('-g','-j') to assist in this.

As you fetch items they are taken off the stack.

Examples
---
Simple request to get all image metadata
```php
$data=array('-*:*','test1.jpg');
$exif = ExifToolBatch::getInstance(/path/to/exiftool');
$exif->add($data);
$result=$exif->fetchAll();
```
