Push.ly Automatic Updates
=========================

Include in your plugin or theme to provide automatic updates to the
users through Push.ly server.


Usage
-----

1. Register to Push.ly if you haven't already ;)

2. Put the library directory in your plugin or theme directory and include
the init file:

```php
include( get_template_directory() . '/automatic-updates/init.php' );
```

3. Add a field on your plugin settings page or your theme options
page where users will enter their email and make it save to the option
`pushly_email`, or simply use the default settings page:

```php
// Rename this function to avoid conflict with other themes or plugins
function test_admin() {
	if ( is_admin() )
		include( get_template_directory() . '/automatic-updates/admin.php' );
}
add_action( 'init', 'test_admin' );
```


Contributing
------------

Bug reports, pull requests and general feedback are always welcome!


License
-------

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

