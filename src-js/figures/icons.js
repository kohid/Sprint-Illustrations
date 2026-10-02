/**
 * Small line icons for the studio's rail and toolbar (24 × 24, drawn with the current text colour).
 */
const PATHS = {
	start: 'M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4L12 3zM18 15l.9 2.1L21 18l-2.1.9L18 21l-.9-2.1L15 18l2.1-.9L18 15z',
	pose: 'M12 4.5a2 2 0 100 .01M12 8v6m0 0l-3.5 6M12 14l3.5 6M6 10.5l6-2.5 6 2.5',
	face: 'M12 3.5a8.5 8.5 0 100 17 8.5 8.5 0 000-17zM9 10v1.2M15 10v1.2M8.8 14.5c1 1.4 2.2 2 3.2 2s2.2-.6 3.2-2',
	hair: 'M5 12c0-4.2 3-7.5 7-7.5s7 3.3 7 7.5c-1-1.6-2.6-2.6-4.4-3.2C12.200 10 8 10.200 5 12zM5 12v6m14-6v6',
	gear: 'M4 15c0-4.200 3.600-7.500 8-7.500s8 3.300 8 7.500M3 15h18M12 7.500V5.500',
	outfit: 'M9 4l-5 3 2 4 2-1v10h8V10l2 1 2-4-5-3c-.6 1.200-1.700 2-3 2S9.600 5.200 9 4z',
	scene: 'M4 5.500h16v13H4zM4 15l4.500-4.500 4 4 2.500-2.500L20 16M15.500 9.200a1 1 0 100 .01',
	save: 'M5 4h11l3 3v13H5zM8 4v5h7V4M8 20v-6h8v6',
	undo: 'M9 7L4.500 11.500 9 16M5 11.500h9.500a5 5 0 010 10H12',
	redo: 'M15 7l4.500 4.500L15 16m4-4.500H9.500a5 5 0 000 10H12',
	random: 'M4 7h3.500c3 0 4 10 8 10H20M4 17h3.500c1.400 0 2.300-1.200 3.200-2.800M20 7h-4.500c-1.600 0-2.700 1.300-3.600 3M17.500 4.500L20 7l-2.500 2.500M17.500 14.500L20 17l-2.500 2.500',
	mirror: 'M12 4v16M8 7L3 12l5 5V7zM16 7l5 5-5 5V7z',
	grid: 'M4 8h16M4 16h16M9 4v16M15 4v16',
	share: 'M10 14a4 4 0 005.700 0l3-3a4 4 0 00-5.700-5.700l-1 1M14 10a4 4 0 00-5.700 0l-3 3A4 4 0 0011 18.700l1-1',
	download: 'M12 4v11m0 0l-4-4m4 4l4-4M5 19h14',
	check: 'M5 12.500l4.500 4.500L19 7.500',
	plus: 'M12 5v14M5 12h14',
	minus: 'M5 12h14',
	pin: 'M9 4h6l-1 6 3 3H7l3-3-1-6zM12 13v7',
	lock: 'M7 11V8a5 5 0 0110 0v3M6 11h12v9H6z',
	copy: 'M9 9h10v11H9zM5 15V4h10',
};

/**
 * One icon.
 *
 * @param {Object} props      Props.
 * @param {string} props.name Icon name.
 * @param {number} props.size Pixel size.
 * @return {Element} SVG icon.
 */
export default function Icon( { name, size = 20 } ) {
	return (
		<svg
			className="si-f-icon"
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.7"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			<path d={ PATHS[ name ] || '' } />
		</svg>
	);
}
