# CORE 0.1.144 — Babylon.js Matrix API Compatibility Hotfix

This small hotfix addresses a startup-breaking runtime exception in the 0.1.143 planetary character-orientation code.

The previous code called `BABYLON.Matrix.FromXYZAxes`, which is not available in the Babylon.js CDN build loaded by CORE. The rotation basis is now constructed explicitly with `BABYLON.Matrix.FromValues` and passed to `BABYLON.Quaternion.FromRotationMatrix`.

This update preserves the 0.1.143 movement and planetary presentation work. It does not change universe generation or persistent player/world data.

Browser runtime verification remains outstanding until the updated build is installed and launched.
