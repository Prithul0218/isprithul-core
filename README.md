# isprithul-core · media branch

Project photos for the starter-content importer in the `isprithul-core` plugin.
The plugin's importer downloads these into the WordPress Media Library once; after
that, photos are managed in WordPress (Projects › Edit › Photos).

All files are resized to 1600 px on the long edge, converted to sRGB and stripped
of EXIF/GPS metadata. `photos.json` lists each file with its project and alt text.

This branch is not part of the plugin itself (Git Updater installs `main`).
