<?php
/*------------------------------------------------------------------------------
  RTk.GPIO
  PHP drivers for the Ryanteck RTk.GPIO interface
  Designed to match the official RPi.GPIO python drivers as closely as possible
--------------------------------------------------------------------------------
  Daniel Bull
  daniel@neonhorizon.co.uk
------------------------------------------------------------------------------*/
namespace PHP_GPIO\RTk;


class GPIO
{
  // Board info
  const RPI_REVISION = 3;
  const VERSION = 'RTk_GPIO 1.1.1';

  // Pin Numbering
  const BCM = 0;
  const BOARD = 1;
  const PINS = array(
    // BOARD => BCM
    27 => 0,
    28 => 1,
    3  => 2,
    5  => 3,
    7  => 4,
    29 => 5,
    31 => 6,
    26 => 7,
    24 => 8,
    21 => 9,
    19 => 10,
    23 => 11,
    32 => 12,
    33 => 13,
    8  => 14,
    10 => 15,
    36 => 16,
    11 => 17,
    12 => 18,
    35 => 19,
    38 => 20,
    40 => 21,
    15 => 22,
    16 => 23,
    18 => 24,
    22 => 25,
    37 => 26,
    13 => 27,
  );

  // Directions
  const IN = 'I';
  const OUT = 'O';

  // Pull up/down
  const PUD_OFF = 'N';
  const PUD_DOWN = 'D';
  const PUD_UP = 'U';

  // Values
  const LOW = 0;
  const HIGH = 1;

  // Actions
  const READ = '?';

  // Settings
  private $diagnostics = FALSE;
  private $device = NULL;
  private $stream = FALSE;
  private $mode = NULL;
  private $warnings = TRUE;


  /*------------------------------------------------------------------------------
    Constructor
  ------------------------------------------------------------------------------*/
  public function __construct($device = NULL, $diagnostics = FALSE)
  {
    // Diagnostics
    if(!is_bool($diagnostics))
      trigger_error('Diagnostics mode must be TRUE or FALSE', E_USER_ERROR);
    $this->diagnostics = $diagnostics;

    // Get device name
    $this->device = isset($device) && $device ? $device : $this->find_rtk_gpio();

    // Sanity
    if(filetype($this->device) !== 'char')
      trigger_error($this->device.' is not an RTk.GPIO', E_USER_ERROR);

    if(!is_writable($this->device))
      trigger_error('Cannot write to '.$this->device.', is your RTk.GPIO connected?', E_USER_ERROR);

    // Set the speed
    exec('stty -F '.escapeshellarg($this->device).' speed 230400 min 0 -brkint -icrnl -imaxbel -opost -onlcr -isig -icanon -iexten -echo -echoe -echok -echoctl -echoke', $output, $result);
    if($result !== 0)
      trigger_error('Cannot set '.$this->device.' to 230400 baud, is this definitely a RTk.GPIO?', E_USER_ERROR);

    // Open the port
    if(($this->stream = fopen($this->device, 'r+b')) === FALSE)
      trigger_error('Cannot access '.$this->device, E_USER_ERROR);

    // Cleanup
    $this->cleanup();
  }


  /*------------------------------------------------------------------------------
    Set pin numbering scheme
  ------------------------------------------------------------------------------*/
  public function setmode($mode)
  {
    // Sanity
    if(!in_array($mode, array(self::BCM, self::BOARD), TRUE))
      trigger_error('Invalid pin numbering scheme, use setmode(GPIO::BCM) or setmode(GPIO::BOARD)', E_USER_ERROR);

    // Set
    $this->mode = $mode;
  }


  /*------------------------------------------------------------------------------
    Get pin numbering scheme
  ------------------------------------------------------------------------------*/
  public function getmode()
  {
    // Get
    return $this->mode;
  }


  /*------------------------------------------------------------------------------
    Configure whether to display warnings
  ------------------------------------------------------------------------------*/
  public function setwarnings($enable)
  {
    // Sanity
    if(!is_bool($enable))
      trigger_error('Invalid setting use setwarnings(TRUE) or setwarnings(FALSE)', E_USER_ERROR);

    // Set
    $this->setwarnings = $enable;
  }


  /*------------------------------------------------------------------------------
    Set the GPIO pin mode
  ------------------------------------------------------------------------------*/
  public function setup($pin, $direction, $settings = array())
  {
    // Handle a list of pins
    if(is_array($pin))
    {
      foreach($pin as $this_pin)
        $this->setup($this_pin, is_array($direction) ? array_shift($direction) : $direction, $settings);
      return;
    }

    // Sanity
    if(!in_array($direction, array(self::IN, self::OUT), TRUE))
      trigger_error('Invalid direction, use GPIO::IN or GPIO::OUT', E_USER_ERROR);

    // Set direction
    if($this->diagnostics) echo $this->device.': -> '.$this->sanitise_pin($pin).$direction.PHP_EOL;
    fwrite($this->stream, $this->sanitise_pin($pin).$direction);

    // Process additional settings
    foreach($settings as $setting => $value)
      switch($setting)
      {
        case 'initial':
          $this->output($pin, $value);
          break;

        case 'pull_up_down':
          $this->pull_up_down($pin, $value);
          break;

        default:
          trigger_error('Invalid setting '.$setting.', correct values are initial or pull_up_down', E_USER_ERROR);
          break;
      }
  }


  /*------------------------------------------------------------------------------
    Set the GPIO level
  ------------------------------------------------------------------------------*/
  public function output($pin, $level)
  {
    // Handle a list of pins
    if(is_array($pin))
    {
      foreach($pin as $this_pin)
        $this->output($this_pin, is_array($level) ? array_shift($level) : $level);
      return;
    }

    // Sanity
    if(!in_array($level, array(self::LOW, self::HIGH), TRUE))
      trigger_error('Invalid level, use GPIO::LOW or GPIO::HIGH or 0 or 1', E_USER_ERROR);

    // Set level
    if($this->diagnostics) echo $this->device.': -> '.$this->sanitise_pin($pin).$level.PHP_EOL;
    fwrite($this->stream, $this->sanitise_pin($pin).$level);
  }


  /*------------------------------------------------------------------------------
    Read the GPIO level
  ------------------------------------------------------------------------------*/
  public function input($pin)
  {
    // Send request
    $sanitised_pin = $this->sanitise_pin($pin);
    if($this->diagnostics) echo $this->device.': -> '.$sanitised_pin.self::READ.PHP_EOL;
    fwrite($this->stream, $sanitised_pin.self::READ);

    // Check each output from the device until we get the answer we are looking for
    do
    {
      // Wait for a response (up to 2 seconds)
      $read = array($this->stream);
      $write = array();
      $except = array();
      stream_select($read, $write, $except, 2);

      // Read data
      $response = trim(fread($this->stream, 2));
      if($this->diagnostics) echo $this->device.': <- '.$response.PHP_EOL;
    }
    while(strlen($response) !== 2 || $response[0] !== $sanitised_pin || !in_array($response, array(GPIO::LOW, GPIO::HIGH)));

    return $response[1] + 0;
  }


  /*------------------------------------------------------------------------------
    Set the pull up/down mode for a GPIO pin
  ------------------------------------------------------------------------------*/
  private function pull_up_down($pin, $pull)
  {
    // Sanity
    if(!in_array($pull, array(self::PUD_OFF, self::PUD_DOWN, self::PUD_UP), TRUE))
      trigger_error('Invalid pull up/down, use GPIO::PUD_OFF, GPIO::PUD_DOWN or GPIO::PUD_UP', E_USER_ERROR);

    // Set
    if($this->diagnostics) echo $this->device.': -> '.$this->sanitise_pin($pin).$pull.PHP_EOL;
    fwrite($this->stream, $this->sanitise_pin($pin).$pull);
  }


  /*------------------------------------------------------------------------------
    Reset the GPIO
  ------------------------------------------------------------------------------*/
  public function cleanup()
  {
    // Reset the pins
    $this->mode = self::BCM;
    foreach(self::PINS as $pin)
      $this->setup($pin, self::IN, array('pull_up_down' => self::PUD_OFF));

    // Reset the mode
    $this->mode = NULL;
  }


  /*------------------------------------------------------------------------------
    Sanitise a pin number doing the required conversions to the RTk.GPIO API
  ------------------------------------------------------------------------------*/
  private function sanitise_pin($pin)
  {
    // Sanity
    if(!is_numeric($pin))
      trigger_error($pin.' is not a valid pin number', E_USER_ERROR);

    // BCM mode
    if($this->mode === self::BCM && in_array($pin, self::PINS))
      return chr($pin + ord('a'));

    // BOARD mode
    if($this->mode === self::BOARD && array_key_exists($pin, self::PINS))
      return chr(self::PINS[$pin] + ord('a'));

    // Something is wrong check the mode
    $this->setmode($this->mode);

    // Otherwise the pin must be bogus
    trigger_error($pin.' is not a valid pin number', E_USER_ERROR);
  }


  /*------------------------------------------------------------------------------
    Find the device name for the RTk.GPIO interface
  ------------------------------------------------------------------------------*/
  private function find_rtk_gpio()
  {
    // Check we have a list of serial devices
    if(!is_dir('/dev/serial/by-id'))
      trigger_error('Cannot find any USB serial devices, is your RTk.GPIO connected?', E_USER_ERROR);

    // Scan them
    foreach(scandir('/dev/serial/by-id') as $device)
      if($device == 'usb-1a86_USB2.0-Serial-if00-port0' && is_link('/dev/serial/by-id/'.$device))
        return realpath('/dev/serial/by-id/'.readlink('/dev/serial/by-id/'.$device));

    trigger_error('Cannot find your RTk.GPIO, is it connected?', E_USER_ERROR);
  }


}
