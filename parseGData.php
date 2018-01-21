<?php

class GData {
   public static function getSpreadsheetData($key, $rowFormatArr, $gid = 'default') {
      
      // Make sure it is public or set to Anyone with link can view 
      $url = 'https://spreadsheets.google.com/feeds/list/' . $key . '/' . $gid . '/public/values?alt=json';
      $url = 'https://spreadsheets.google.com/feeds/list/key/' . $key . '/private/full';

      $content = file_get_contents($url);
      krumo($content);
      $contentArr = json_decode($content, true);
      $rows = array();
      foreach($contentArr['feed']['entry'] as $row) {
         if ($row['title']['$t'] == '-') {
            continue;
         }
         $rowItems = array();
         foreach($rowFormatArr as $item) {
            $rowItems[$item] = self::getRowValue($row['content']['$t'], $rowFormatArr, $item);
         }
         $rows[] = $rowItems;
      }
      return $rows;
   }
   static function getRowValue($row, $rowFormatArr, $column_name) {
      echo "getRowValue[$column_name]:$row";
      if (empty($column_name)) {
         throw new Exception('column_name must not empty');
      }
      $begin = strpos($row, $column_name);
      echo "begin:$begin";
      
      if ($begin == -1) {
         return '';
      }
      $begin = $begin + strlen($column_name) + 1;
      
      $end = -1;
      $found_begin = false;
      foreach($rowFormatArr as $entity) {
         echo "checking:$entity";
         if ($found_begin && strpos($row, $entity) != -1) {
            $end = strpos($row, $entity) - 2;
            echo "end1:$end";
            break;
         }
         if ($entity == $column_name) {
            $found_begin = true;
         }
         #check if last element
         if (substr($row, strlen($row) - 1) == $column_name) {
            $end = strlen($row);
         } else {
            if ($end == -1) {
               $end = strlen($row);
            } else {
               $end = $end + 2;
            }
         }
      }
      echo "end:$end";
      echo "$column_name:$row";
      $value = substr($row, $begin, $end - $begin);
      $value = trim($value);
      echo "${column_name}[${begin}-${end}]:[$value]";
      return $value;
   }
}