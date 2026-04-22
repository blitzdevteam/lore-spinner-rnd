import React from 'react';
import waterTexture from './water_resized150.png';

/**
 * A full‑screen component that renders a rich, dark ocean texture as the
 * background. The texture is imported from an external PNG file (the
 * downscaled user‑provided image) and animated to give a subtle sense
 * of drifting water. A radial gradient overlay in the brand colour
 * (#54f4da) adds depth without overwhelming the darkness.
 */
export default function OceanTexture() {
  return (
    <div className="ocean-container">
      <div className="ocean-background" />
      <div className="ocean-overlay" />
    </div>
  );
}

// Inject styles into the document head so the component is self‑contained.
const style = document.createElement('style');
style.textContent = `
  .ocean-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: #1a1a1a;
  }

  .ocean-background {
    position: absolute;
    top: -5%;
    left: -5%;
    width: 110%;
    height: 110%;
    background-image: url(${waterTexture});
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center;
    animation: ocean-drift 60s linear infinite;
    filter: brightness(1.2) contrast(1.1) saturate(1.2);
  }

  .ocean-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    background: radial-gradient(circle at 50% 30%, rgba(84, 244, 218, 0.3), rgba(26, 26, 26, 0.9));
    mix-blend-mode: screen;
  }

  @keyframes ocean-drift {
    0% {
      background-position: 50% 0;
    }
    100% {
      background-position: 50% 100%;
    }
  }
`;
document.head.appendChild(style);