# CORE 0.1.172 — Constellation Map Rendering Fix

- Explicitly activates and retains the Babylon.js Constellation camera instead of relying on implicit active-camera selection.
- Forces the WebGL drawing buffer to the visible canvas dimensions after the gameplay layout is revealed.
- Refreshes the camera view matrix and performs an initial scene render after map geometry and focus are established.
- Increases ambient illumination for clearer system markers.
- Reports asynchronous scene-render failures in the map panel instead of leaving an unexplained blank canvas.
- Adds regression coverage for camera activation, canvas sizing, and initial render safeguards.

Browser/WebGL visual verification remains pending installation in the target environment.
