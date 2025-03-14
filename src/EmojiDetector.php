<?php
namespace EmojiDetector;

define("LONGEST_EMOJI", 8);

class EmojiDetector
{
	public function detectEmoji($string)
	{
		// Find all the emoji in the input string
		$prevencoding = mb_internal_encoding();
		mb_internal_encoding("UTF-8");

		$data = [];

		static $map;
		if (!isset($map)) {
			$map = $this->loadMap();
		}

		static $regexp;
		if (!isset($regexp)) {
			$regexp = $this->loadRegexp();
		}

		if (preg_match_all($regexp, $string, $matches, PREG_OFFSET_CAPTURE)) {
			$lastGOffset = 0;
			foreach ($matches[0] as $match) {
				$ch = $match[0]; // the actual emoji char found by the regex, may be multiple bytes
				$mbLength = mb_strlen($ch); // the length of the emoji, mb chars are counted as 1

				$offset = $match[1];

				// echo mb_strlen($string)." found emoji length: ".strlen($ch)." lastGOffset: $lastGOffset mbLength: $mbLength\n";

				$gOffset = grapheme_strpos($string, $ch, $lastGOffset);
				$lastGOffset = $gOffset + 1;

				$points = [];
				for ($i = 0; $i < $mbLength; $i++) {
					$points[] = strtoupper(dechex($this->uniord(mb_substr($ch, $i, 1))));
				}
				$hexstr = implode("-", $points);

				if (array_key_exists($hexstr, $map)) {
					$short_name = $map[$hexstr];
				} else {
					$short_name = null;
				}

				$skin_tone = null;
				$skin_tones = [
					"1F3FB" => "skin-tone-2",
					"1F3FC" => "skin-tone-3",
					"1F3FD" => "skin-tone-4",
					"1F3FE" => "skin-tone-5",
					"1F3FF" => "skin-tone-6",
				];
				foreach ($points as $pt) {
					if (array_key_exists($pt, $skin_tones)) {
						$skin_tone = $skin_tones[$pt];
					}
				}

				$data[] = [
					"emoji" => $ch,
					"short_name" => $short_name,
					"num_points" => mb_strlen($ch),
					"points_hex" => $points,
					"hex_str" => $hexstr,
					"skin_tone" => $skin_tone,
					"byte_offset" => $offset, // The position of the emoji in the string, counting each byte
					"grapheme_offset" => $gOffset, // The grapheme-based position of the emoji in the string
				];
			}
		}

		if ($prevencoding) {
			mb_internal_encoding($prevencoding);
		}

		return $data;
	}

	public function getFirstEmoji($string)
	{
		$emojis = $this->detectEmoji($string);
		if (count($emojis)) {
			return $emojis[0];
		} else {
			return null;
		}
	}

	public function isSingleEmoji($string)
	{
		$prevencoding = mb_internal_encoding();
		mb_internal_encoding("UTF-8");

		// If the string is longer than the longest emoji, it's not a single emoji
		if (mb_strlen($string) >= LONGEST_EMOJI) {
			return false;
		}

		$all_emoji = $this->detectEmoji($string);

		$emoji = false;

		// If there are more than one or none, return false immediately
		if (count($all_emoji) == 1) {
			$emoji = $all_emoji[0];

			// Check if there are any other characters in the string

			// Remove the emoji found
			$string = str_replace($emoji["emoji"], "", $string);

			// If there are any characters left, then the string is not a single emoji
			if (strlen($string) > 0) {
				$emoji = false;
			}
		}

		if ($prevencoding) {
			mb_internal_encoding($prevencoding);
		}

		return $emoji;
	}

	public function replaceEmoji($string, $prefix = "", $suffix = "")
	{
		while ($emoji = $this->getFirstEmoji($string)) {
			$offset = $emoji["byte_offset"];
			$length = strlen($emoji["emoji"]);
			$strlen = strlen($string);
			$start = substr($string, 0, $offset);
			$end = substr($string, $offset + $length, $strlen - ($offset + $length));
			$string = $start . $prefix . $emoji["short_name"] . $suffix . $end;
		}
		return $string;
	}

	private function loadMap()
	{
		return json_decode(file_get_contents(dirname(__FILE__) . "/map.json"), true);
	}

	private function loadRegexp()
	{
		return "/(?:" . json_decode(file_get_contents(dirname(__FILE__) . "/regexp.json")) . ")/u";
	}

	public function uniord($c)
	{
		$ord0 = ord($c[0]);
		if ($ord0 >= 0 && $ord0 <= 127) {
			return $ord0;
		}
		$ord1 = ord($c[1]);
		if ($ord0 >= 192 && $ord0 <= 223) {
			return ($ord0 - 192) * 64 + ($ord1 - 128);
		}
		$ord2 = ord($c[2]);
		if ($ord0 >= 224 && $ord0 <= 239) {
			return ($ord0 - 224) * 4096 + ($ord1 - 128) * 64 + ($ord2 - 128);
		}
		$ord3 = ord($c[3]);
		if ($ord0 >= 240 && $ord0 <= 247) {
			return ($ord0 - 240) * 262144 + ($ord1 - 128) * 4096 + ($ord2 - 128) * 64 + ($ord3 - 128);
		}
		return false;
	}
}
