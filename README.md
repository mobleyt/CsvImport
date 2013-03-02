
CSV Import (plugin for Omeka) [fork]
=============================


Summary
-------

Allows users to import items from a simple CSV (comma separated values) file,
and then map the CSV column data to multiple elements, files, and/or tags. Each
row in the file represents metadata for a single item. This plugin is useful
for exporting data from one database and importing that data into an Omeka site.

This plugin is a fork of the original plugin that allows:
* use of tabulation as a separator,
* import of metadata of files,
* import of files one by one to avoid overloading server,
* compatibily with [XmlImport][1].

For more information on Omeka, see [Omeka][2].


Installation
------------

Uncompress files and rename plugin folder "CsvImport".

Then install it like any other Omeka plugin and follow the config instructions.


Warning
-------

Use it at your own risk.

It's always recommended to backup your database so you can roll back if needed.


Troubleshooting
---------------

See online issues on [GitHub][3] (original plugin) and [GitHub][4] (fork).


License
-------

This plugin is published under [GNU/GPL][5].

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.


Contact
-------

Current maintainers:

Original plugin:
* Center for History and New Media (see [CHNM][6])

Forked plugin:
* Daniel Berthereau (see [Daniel-KM][7])
* Shawn Averkamp (see [saverkamp][8])

This plugin has been forked for [ENPC / École des Ponts ParisTech][9]) and [Pop Up Archive][10]).


Copyright
---------

Original plugin:
* Copyright Center for History and New Media, 2012

Forked plugin:
* Copyright Daniel Berthereau, 2012-2013
* Copyright Shawn Averkamp, 2012


[1]: https://github.com/Daniel-KM/XmlImport "GitHub XmlImport"
[2]: https://omeka.org "Omeka.org"
[3]: https://github.com/omeka/plugin-CsvImport/Issues "GitHub CsvImport"
[4]: https://github.com/saverkamp/plugin-CsvImport/Issues "GitHub CsvImport fork"
[5]: https://www.gnu.org/licenses/gpl-3.0.html "GNU/GPL"
[6]: https://github.com/omeka "CHNM"
[7]: https://github.com/Daniel-KM "Daniel Berthereau"
[8]: https://github.com/saverkamp "saverkamp"
[9]: http://bibliotheque.enpc.fr "École des Ponts ParisTech"
[10]: http://popuparchive.org/ "Pop Up Archive"
