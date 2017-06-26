RTk.GPIO PHP
============

- **Description:** PHP drivers for the Ryanteck RTk.GPIO interface
- **Project Website:** [GitHub](https://github.com/NeonHorizon/RTk.PHP)
- **Requirements:** An Ubuntu Linux installation or equivalent
- **License:** GPL Version 3

### Description

The RTk.GPIO is a USB device designed to provide a GPIO interface in the style of a Raspberry Pi to a regular PC and is available from many online retailers including [RyanTeck](https://ryanteck.uk/) directly.

This library is a driver for the RTk.GPIO for those that prefer (or have a requirement to) use PHP instead of Python.

It has been designed to match the official RPi.GPIO and RTk.GPIO python driver coding interfaces as closely as possible but obviously with PHP syntax.

One of the reasons for its existence is PHP is particularly suited to making web interfaces. This means it's often beneficial to use PHP instead of Python if you require a web interface for your RTk.GPIO.

Included is an LCD driver for the common LCD's on the market

![RTk.GPIO](https://ryanteck.uk/671-large_default/rtkgpio.jpg)

---

### Installation instructions

Either [Download the latest zip file](https://github.com/NeonHorizon/RTk.GPIO/archive/master.zip) and extract it, or git clone https://github.com/NeonHorizon/RTk.GPIO.git

The project contains the following files...

* **PHP_GPIO/RTk.GPIO.php** - this is the main driver (the file you need for your own projects)
* **PHP_GPIO/Hitachi.LCD.php** - this is a Hitachi LCD driver (such as the common 16x2 LCDs) which can be used with RTk.GPIO
* **gpio_examples** - A script with some examples of how to use the RTk.GPIO driver
* **lcd_examples** - A script with some examples of how to use the Hitachi.LCD driver
* **gpio_random_output** - An example script that changes all the GPIO's to outputs and randomly blinks them
* **gpio_read_all** - An example script that changes all the GPIO's to inputs and notifies when they go high or low
* **gpio_brutal_test** - A script that brutally tests the RTk.GPIO by randomly changing the GPIO pin settings and reading/writing to them
* **README.md** - This file
* **COPYING.txt** - The GPLv3 license

Presumably you already have PHP installed but if you don't install it:

Current Ubuntu:
```
sudo apt -y install php-cli
```

Ubuntu 14.04:
```
sudo apt-get -y install php5-cli
```

---

### Quick Start

The easiest way to make a start is to take a look at the [examples file](https://github.com/NeonHorizon/RTk.GPIO/blob/master/examples) which was part of your download. This includes descriptions of most of the commands you can perform.
You can test this script directly by plugging your RTk.GPIO into your computer (with nothing connected to the GPIO) and running the examples file from inside the directory:

```
cd RTk.GPIO
./examples
```

---

### Using the Driver

Using the driver in your own scripts is fairly simple...

If you want to run your script directly from the command line it must start by telling Linux it's PHP and then include the php opening tag:
```
#!/usr/bin/php
<?php
```

Now we are in the PHP code; load the RTk.GPIO driver and tell it you want to use it by the name GPIO:
```
require_once('PHP_GPIO/RTk.GPIO.php');
use PHP_GPIO\RTK\GPIO as GPIO;
```

Open a serial connection to your RTk.GPIO and call it $GPIO (see the examples file of how to use multiple RTk.GPIO's):
```
$GPIO = new GPIO();
```

Specify the pin numbering scheme:
```
$GPIO->setmode(GPIO::BCM);
```

Send your first commands!

For example here we set pin 4 to be an output and set it high:
```
$GPIO->setup(4, GPIO::OUT);
$GPIO->output(4, GPIO::HIGH);
```

---

### License Information

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program.  If not, see [http://www.gnu.org/licenses/](http://www.gnu.org/licenses/).

---

### Credits
[Daniel Bull](https://google.com/+DanielBull)

