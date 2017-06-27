<?php
/*------------------------------------------------------------------------------
  Hitachi.LCD
  PHP drivers for 5v Hitachi HDD44780 or KS0066U Compatible LCD's
--------------------------------------------------------------------------------
  Daniel Bull
  daniel@neonhorizon.co.uk
------------------------------------------------------------------------------*/
namespace PHP_GPIO\Hitachi;


class LCD
{
  // Pins
  const PINS = array(
    'RS' => 'the Register Select (LCD pin 4)',
    'ES' => 'the Enable Signal (LCD pin 6)',
    'D0' => 'Data Bus 0 (LCD pin 7)',
    'D1' => 'Data Bus 1 (LCD pin 8)',
    'D2' => 'Data Bus 2 (LCD pin 9)',
    'D3' => 'Data Bus 3 (LCD pin 10)',
    'D4' => 'Data Bus 4 (LCD pin 11)',
    'D5' => 'Data Bus 5 (LCD pin 12)',
    'D6' => 'Data Bus 6 (LCD pin 13)',
    'D7' => 'Data Bus 7 (LCD pin 14)',
  );

  // Command list
  const CLEAR                = 0b00000001;  // Clear the screen
  const HOME                 = 0b00000010;  // Send the cursor home
  const TEXT_REVERSE         = 0b00000100;  // Type right to left
  const TEXT_REVERSE_SCROLL  = 0b00000101;  // Type right to left and scroll
  const TEXT_FORWARDS        = 0b00000110;  // Type left to right
  const TEXT_FORWARDS_SCROLL = 0b00000111;  // Type left to right and scroll
  const OFF                  = 0b00001000;  // Turn off the display
  const ON_NO_CURSOR         = 0b00001100;  // Turn on the display no cursor
  const ON_CURSOR            = 0b00001110;  // Turn on the display + cursor
  const ON_BLINK_CURSOR      = 0b00001111;  // Turn on the display + blink cursor
  const CURSOR_LEFT          = 0b00010000;  // Move the cursor left
  const CURSOR_RIGHT         = 0b00010100;  // Move the cursor right
  const SCROLL_LEFT          = 0b00011000;  // Scroll left
  const SCROLL_RIGHT         = 0b00011100;  // Scroll right
  const FONT_1_LINE_5X8      = 0b00100000;  // 4bit, 1 line, 5x8 font
  const FONT_1_LINE_5X11     = 0b00100100;  // 4bit, 1 line, 5x11 font
  const FONT_2_LINE_5X8      = 0b00101000;  // 4bit, 2 line, 5x8 font
  const FONT_2_LINE_5X11     = 0b00101100;  // 4bit, 2 line, 5x11 font

  // Timings in ns
  const TIMING_TSU1  = 100;
  const TIMING_TW    = 300;
  const TIMING_TH2   = 10;
  const TIMING_CLEAR = 2000000;

  // Row offsets
  const ROW_OFFSET = array(
    0 => 0,
    1 => 64,
    2 => 20,
    4 => 84,
  );

  // Settings
  private $gpio = NULL;
  public  $bits = NULL;
  public  $cols = NULL;
  public  $rows = NULL;
  public  $pins = array();


  /*------------------------------------------------------------------------------
    Constructor
  ------------------------------------------------------------------------------*/
  public function __construct($gpio = NULL)
  {
    // Sanity
    if(get_class($gpio) !== 'PHP_GPIO\RTk\GPIO')
      trigger_error('A GPIO connection must be supplied when setting up an LCD', E_USER_ERROR);

    $this->gpio = $gpio;
  }


  /*----------------------------------------------------------------------------
    Prepare for use
  ----------------------------------------------------------------------------*/
  public function initialise()
  {
    // Sanity
    if(!in_array($this->bits, array(4, 8), TRUE))
      trigger_error('bits is '.(!isset($this->bits) ? 'not set' : 'invalid').', use 4 or 8', E_USER_ERROR);
    if(!is_numeric($this->cols) || $this->cols < 1 || $this->cols > 127)
      trigger_error('cols is '.(!isset($this->cols) ? 'not set' : 'invalid').', 16 or 20 columns are common types', E_USER_ERROR);
    if(!is_numeric($this->rows) || $this->rows < 1 || $this->rows > 4)
      trigger_error('rows is '.(!isset($this->rows) ? 'not set' : 'invalid').', 2 or 4 rows are common types', E_USER_ERROR);
    foreach(self::PINS as $name => $description)
      if($name[0] != 'D' || $name[1] > 7 - $this->bits)
        if(!isset($this->pins[$name])) // Full validation done by RTk/GPIO library
          trigger_error('pins['.$name.'] is not set, which GPIO pin is '.$description.' connected to?', E_USER_ERROR);
    foreach($this->pins as $name => $pin)
      if(!array_key_exists($name, self::PINS))
        trigger_error('Unknown pin '.$name.' configured?', E_USER_ERROR);

    // Set all pins as outputs
    foreach($this->pins as $name => $pin)
      $this->gpio->setup($this->pins, $this->gpio::OUT);

    // Switch into 4 bit mode if applicable
    if($this->bits == 4)
      $this->command(0b00110011, 0b00110010);

    // Reset
    $this->command(self::FONT_2_LINE_5X8, self::ON_NO_CURSOR, self::CLEAR, self::TEXT_FORWARDS);
  }


  /*----------------------------------------------------------------------------
    Output a string
  ----------------------------------------------------------------------------*/
  public function output($string)
  {
    // Go into character mode
    if($this->set_mode(TRUE));

    // Send string
    $line = 1;
    for($i = 0; $i < strlen($string); $i++)
      if($string[$i] == "\n")
      {
        // Newline
        $this->position(0, $line++);
        $this->set_mode(TRUE);
      }
      elseif($string[$i] != "\r")
        // Send characters
        $this->send_byte(ord($string[$i]));
  }


  /*----------------------------------------------------------------------------
    Position the cursor
  ----------------------------------------------------------------------------*/
  public function position($x = 0, $y = 0)
  {
    // Sanity
    if(!is_numeric($x))
      trigger_error('x is not a number, use position(x, y) and where x and y are the column and row numbers', E_USER_ERROR);
    if(!is_numeric($y))
      trigger_error('y is not a number, use position(x, y) and where x and y are the column and row numbers', E_USER_ERROR);
    if($x < 0 || $x >= $this->cols)
      trigger_error('x must be between 0 and '.($this->cols - 1), E_USER_ERROR);
    if($y < 0 || $y >= $this->rows)
      trigger_error('y must be between 0 and '.($this->rows - 1), E_USER_ERROR);

    // Calculate byte position
    $pos = $x + self::ROW_OFFSET[$y];

    // Check its within range
    if($pos < 0 || $pos > 0b01111111)
      trigger_error('position() is out of range', E_USER_ERROR);

    // Set
    $this->command($pos + 0b10000000);
  }


  /*----------------------------------------------------------------------------
    Execute single or multiple commands
  ----------------------------------------------------------------------------*/
  public function command()
  {
    // Get the commands
    $commmands = func_get_args();

    // Sanity
    if(count($commmands) < 1) return;

    // Go into command mode
    $this->set_mode(FALSE);

    // Execute commands
    foreach($commmands as $command)
    {
      // Sanity
      if(!is_numeric($command) || $command < 0 || $command > 255)
        trigger_error('Invalid command()', E_USER_ERROR);

      $this->send_byte($command);

      if($command == self::CLEAR || $command == self::HOME)
        time_nanosleep(0, self::TIMING_CLEAR);
    }
  }


  /*----------------------------------------------------------------------------
    Switch between character mode and command mode
  ----------------------------------------------------------------------------*/
  private function set_mode($character_mode)
  {
    // Make sure the enable line is low before we start
    $this->gpio->output($this->pins['ES'], 0);

    // Set Register Select to the correct mode
    $this->gpio->output($this->pins['RS'], $character_mode ? 1 : 0);

    // Wait for the display to catch up
    time_nanosleep(0, self::TIMING_TSU1);
  }


  /*----------------------------------------------------------------------------
    Send a byte of data
  ----------------------------------------------------------------------------*/
  private function send_byte($value)
  {
    // Check for nonsense
    if(!is_numeric($value) || $value < 0 || $value > 255)
      trigger_error('Invalid send_byte()', E_USER_ERROR);

    // Set high nibble
    $this->gpio->output($this->pins['D4'], ($value & 0b00010000) > 0 ? 1 : 0);
    $this->gpio->output($this->pins['D5'], ($value & 0b00100000) > 0 ? 1 : 0);
    $this->gpio->output($this->pins['D6'], ($value & 0b01000000) > 0 ? 1 : 0);
    $this->gpio->output($this->pins['D7'], ($value & 0b10000000) > 0 ? 1 : 0);

    // Srobe if we are in 4 bit mode
    if($this->bits == 4)
      $this->strobe();

    // Set low nibble
    $this->gpio->output($this->pins[$this->bits == 4 ? 'D4' : 'D1'], ($value & 0b00000001) > 0 ? 1 : 0);
    $this->gpio->output($this->pins[$this->bits == 4 ? 'D5' : 'D2'], ($value & 0b00000010) > 0 ? 1 : 0);
    $this->gpio->output($this->pins[$this->bits == 4 ? 'D6' : 'D3'], ($value & 0b00000100) > 0 ? 1 : 0);
    $this->gpio->output($this->pins[$this->bits == 4 ? 'D7' : 'D4'], ($value & 0b00001000) > 0 ? 1 : 0);

    // Strobe data
    $this->strobe();

    return TRUE;
  }


  /*----------------------------------------------------------------------------
    Strobe the enable line
  ----------------------------------------------------------------------------*/
  private function strobe()
  {
    $this->gpio->output($this->pins['ES'], 1);
    time_nanosleep(0, self::TIMING_TW);
    $this->gpio->output($this->pins['ES'], 0);
    time_nanosleep(0, self::TIMING_TH2);
  }


}
