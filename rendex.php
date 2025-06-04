<?php 

function PhpBlade($code) {
  $code = preg_replace("/{{\?(.+)}}/","<?= @isset($1) ? $1 : '' ?>",$code);
  $code = str_replace("{{","<?= ",$code);
  
  $code = str_replace("}}\n"," ?> \n",$code);
  $code = str_replace("}}"," ?>",$code);

  $code = preg_replace("/@foreach\( *([^\,\ ]+)[, ]+([^\,\ ]+)[, ]+([^\,\ ]+) *\)/","<?php foreach( $1 as $2 => $3){?>",$code);
  $code = preg_replace("/^\s*@else if(.+)/m","<?php } else if $1 { ?>",$code);
  $code = preg_replace("/^\s*@if\((.+)\)/m","<?php if ($1) {?>",$code);
  $code = preg_replace("/@if\((.+)\)/","<?php if ($1) {?>",$code);
  $code = preg_replace("/@elseif(.+)/","<?php } else if $1 {?>",$code);
  $code = preg_replace("/@else if(.+)/","<?php } else if $1 {?>",$code);
  $code = str_replace("@else","<?php } else {?>",$code);
  $code = preg_replace("/^\s*@end(.*)/m","<?php } ?>",$code);

  return $code;
}


function JSBlade($code) {
  $output = [];
  $lines = explode("\n", $code);

  foreach ($lines as $line) {
    $trimmed = trim($line);

    // Skip empty
    if ($trimmed === '') {
      $output[] = '';
      continue;
    }

    // Control structures
    if (preg_match('/@foreach\( *([^\,\ ]+)[, ]+([^\,\ ]+)[, ]+([^\,\ ]+) *\)/', $trimmed, $m)) {
      $output[] = "for (let [{$m[2]}, {$m[3]}] of Object.entries({$m[1]})) {";
    } elseif (preg_match('/@if\s*\((.+)\)/', $trimmed, $m)) {
      $output[] = "if ({$m[1]}) {";
    } elseif (preg_match('/@elseif\s*\((.+)\)/', $trimmed, $m)) {
      $output[] = "} else if ({$m[1]}) {";
    } elseif ($trimmed === '@else') {
      $output[] = "} else {";
    } elseif (strpos($trimmed, '@end') === 0) {
      $output[] = "}";
    } else {
      // Replace Blade variables with JS template syntax
      $line = preg_replace_callback('/{{\?(.+?)}}/', fn($m) => '${ typeof ' . trim($m[1]) . ' !== "undefined" ? ' . trim($m[1]) . ' : "" }', $line);
      $line = preg_replace_callback('/{{(.*?)}}/', fn($m) => '${ ' . trim($m[1]) . ' }', $line);
      // Escape backticks
      $line = str_replace('`', '\`', $line);
      $line = trim($line);
      $output[] = "_ += `$line\\n`;";
    }
  }
  return "let _ = \"\";\n" . implode("\n", $output);
}


function endsWith(string $haystack, string $needle): bool {
  $length = strlen($needle);
  if ($length === 0) {
    return true;
  }
  return substr($haystack, -$length) === $needle;
}


function globRecursive($pattern, $flags = 0) {
  $files = glob($pattern, $flags);
  foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
    $files = array_merge($files, globRecursive($dir . '/' . basename($pattern), $flags));
  }
  return $files;
}

function bundle(){
  $libs = globRecursive(__DIR__."/rendex/*");
  $css = "";
  $js  = "";
  $php = ' $Render = [];';
  $jsr = ' $Render = {};';
  foreach ($libs as $file) {
    $file = $file;
    $name = pathinfo($file, PATHINFO_FILENAME);
    
    if (file_exists($file)) {
      $content = file_get_contents($file);
      if (endsWith($file, ".css") !== false) {
        $css .= "/* $file */\n" . $content . "\n";
      } elseif (endsWith($file, "asenac.js") !== false) {
        $js .= ";// $file\n" . AsenacBuilder::build( $content ). "\n";
      } elseif (endsWith($file, ".js") !== false) {
        $js .= ";// $file\n" . $content . "\n";
      } elseif (endsWith($file, ".html") !== false) {
        $php .= ";// $file\n". '$Render["' . $name . '"] = function($_){?>' . PhpBlade($content) . '<?php };'; 
        $jsr .= ";// $file\n". '$Render["' . $name . '"] = function($_){' . JSBlade($content) . ';return _; };'; 
      }
    }
  }
  return ["php"=>$php,"jsr"=>$jsr,"css"=>$css,"js"=>$js];
}

$bundle = bundle();
file_put_contents("final/views.php",'<?php'.$bundle["php"]);
file_put_contents("final/views.js",$bundle["jsr"]);
file_put_contents("final/scripts.js",$bundle["js"]);
file_put_contents("final/styles.css",$bundle["css"]);
