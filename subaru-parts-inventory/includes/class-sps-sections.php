<?php
/**
 * Subaru service-manual sections (the smaller "sub-boxes").
 *
 * Every parts.subaru.com diagram is assigned to one service-manual section, so a big
 * category box (e.g. Engine and Cooling) can be split into sub-boxes: EN-FU, EN-IN, EN-ME …
 * Diagnostics-only sections of the manual (…(diag)) are not used – they have no parts.
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_Sections {

	/**
	 * Section code => name, in service-manual order.
	 *
	 * @return array<string,string>
	 */
	public static function all() {
		return array(
			// Engine section.
			'FU'  => 'Fuel Injection (Fuel Systems)',
			'EC'  => 'Emission Control',
			'IN'  => 'Intake (Induction)',
			'ME'  => 'Mechanical',
			'EX'  => 'Exhaust',
			'CO'  => 'Cooling',
			'LU'  => 'Lubrication',
			'SP'  => 'Speed Control Systems',
			'IG'  => 'Ignition',
			'SC'  => 'Starting / Charging Systems',
			'EN'  => 'Engine Control (Sensors & Harness)',
			// Transmission section.
			'CS'  => 'Control Systems',
			'6MT' => 'Manual Transmission & Differential',
			'CL'  => 'Clutch System',
			// Chassis section.
			'FS'  => 'Front Suspension',
			'RS'  => 'Rear Suspension',
			'WT'  => 'Wheel and Tire System',
			'TPM' => 'Tire Pressure Monitoring',
			'DI'  => 'Differentials',
			'TC'  => 'Transfer Case',
			'DS'  => 'Drive Shaft System',
			'VDC' => 'Vehicle Dynamics Control (VDC)',
			'BR'  => 'Brake',
			'PB'  => 'Parking Brake',
			'PS'  => 'Power Steering',
			// Body section.
			'AC'  => 'HVAC (Heater, Ventilator & A/C)',
			'AB'  => 'Airbag System',
			'SB'  => 'Seat Belt System',
			'LI'  => 'Lighting System',
			'WW'  => 'Wiper and Washer Systems',
			'ET'  => 'Entertainment',
			'COM' => 'Communication',
			'GW'  => 'Glass / Windows / Mirrors',
			'BS'  => 'Body Structure',
			'IDI' => 'Instrumentation / Driver Info',
			'SE'  => 'Seats',
			'SL'  => 'Security and Locks',
			'SR'  => 'Sunroof',
			'EI'  => 'Exterior / Interior Trim',
			'EB'  => 'Exterior Body Panels',
			'CC'  => 'Cruise Control System',
			'WI'  => 'Wiring System',
			'GN'  => 'General (Tools, Fluids, Labels)',
		);
	}

	/**
	 * Rules per parts.subaru.com category: regex on the raw diagram name => section.
	 * First match wins; the last entry ('' => X) is the category default.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function rules() {
		return array(
			'Engine and Cooling'           => array(
				'/PIPE COMPLE-OIL|OIL PAN|OIL PUMP|OIL COOLER/'                                  => 'LU',
				'/FUEL PIPING|FUEL TANK|FUEL INJECTOR|FUEL PIPE|INSUL-FUEL/'                       => 'FU',
				'/^EMISSION CONTROL/'                                                             => 'EC',
				'/AIR DUCT EXHAUST|EXHAUST PIPE|^EXHAUST/'                                        => 'EX',
				'/AIR CLEANER|^AIR DUCT|INTAKE MANIFOLD BODY|INTAKE MANIFOLD SYSTEM|THROTTLE|INTER COOLER|TURBO CHARGER/' => 'IN',
				'/ENGINE COOLING|WATER PIPE|WATER PUMP/'                                          => 'CO',
				'/SPARK PLUG/'                                                                    => 'IG',
				'/ALTERNATOR|STARTER/'                                                            => 'SC',
				'/WIRING HARNESS|RELAY & SENSOR/'                                                 => 'EN',
				''                                                                                => 'ME',
			),
			'Manual Transmission'          => array(
				'/^MT, CLUTCH/' => 'CL',
				''              => '6MT',
			),
			'Automatic Transmission'       => array(
				'' => 'CS',
			),
			'Driveline and Differential'   => array(
				'/PROPELLER/' => 'DS',
				''            => 'DI',
			),
			'Suspension and Brakes'        => array(
				'/ANTILOCK|V\.D\.C/'                         => 'VDC',
				'/PARKING BRAKE/'                            => 'PB',
				'/BRAKE/'                                    => 'BR',
				'/TPMS/'                                     => 'TPM',
				'/DISK WHEEL|^TIRE|WHEEL CAP/'               => 'WT',
				'/AXLE/'                                     => 'DS',
				'/FRONT SHOCK|FRONT SUSPENSION/'             => 'FS',
				'/REAR SHOCK|REAR SUSPENSION/'               => 'RS',
				''                                           => 'FS',
			),
			'Steering'                     => array(
				'/AIR BAG/'                => 'AB',
				'/CLUTCH CONTROL/'         => 'CL',
				'/GEAR SHIFT/'             => 'CS',
				'/ACCEL PEDAL/'            => 'SP',
				'/BRAKE & CLUTCH PEDAL/'   => 'BR',
				'/FOOT REST/'              => 'EI',
				''                         => 'PS',
			),
			'Electrical'                   => array(
				'/AIRBAG HARNESS/'                                   => 'AB',
				'/^AUDIO/'                                           => 'ET',
				'/BATTERY/'                                          => 'SC',
				'/WASHER|WIPER/'                                     => 'WW',
				'/LAMP|SWITCH \(COMBINATION\)/'                      => 'LI',
				'/POWER WINDOW|MIRROR SWITCH|CONSOLE & DOOR/'        => 'GW',
				'/METER|INSTRUMENT PANEL|HORN|ADA SYSTEM|SWITCH/'    => 'IDI',
				''                                                   => 'WI',
			),
			'Body'                         => array(
				'/GARNISH|MOLDING/'                                              => 'EI',
				'/^KEY KIT/'                                                     => 'SL',
				'/REAR VIEW MIRROR/'                                             => 'GW',
				'/^FENDER$|FRONT HOOD|BUMPER|TRUNK|FUEL FLAP/'                   => 'EB',
				'/BODY PANEL|COWL PANEL|SIDE PANEL|TOE BOARD|^PLUG/'             => 'BS',
				''                                                               => 'EI',
			),
			'Door'                         => array(
				'/GLASS & REGULATOR/' => 'GW',
				'/LATCH & HANDLE/'    => 'SL',
				''                    => 'EB',
			),
			'Heating and Air Conditioning' => array(
				'' => 'AC',
			),
			'Interior'                     => array(
				'/SEAT BELT/'                       => 'SB',
				'/^FRONT SEAT|^REAR SEAT/'          => 'SE',
				'/REAR WINDOW|WINDSHIELD GLASS/'    => 'GW',
				'/^SUN ROOF/'                       => 'SR',
				'/^TOOL KIT/'                       => 'GN',
				''                                  => 'EI',
			),
			'Maintenance'                  => array(
				'/^(Alternator|Batteries|Starter|Hold Downs|Trays)$/'                                         => 'SC',
				'/^(Brake|Calipers|Pads|Rotors|Drums|Shoes|Wheel Cylinder)/'                                  => 'BR',
				'/^Bulbs$/'                                                                                   => 'LI',
				'/^Cable Ends$/'                                                                              => 'PB',
				'/^(Center Caps|Spare Wheels|Tire Tools|Tires|Wheel Covers|Wheel Studs|Wheels)/'               => 'WT',
				'/^TPMS/'                                                                                     => 'TPM',
				'/^Clutch/'                                                                                   => 'CL',
				'/^(Distributor|Ignition Coil|Plug Wires|Spark Plugs)/'                                       => 'IG',
				'/^(Drain Plug|Motor Oil|Oil Filter)/'                                                        => 'LU',
				'/^Engine Air Filter$/'                                                                       => 'IN',
				'/^(PCV Valve|O2 Sensors)/'                                                                   => 'EC',
				'/^(Expansion Tanks|Hoses|Radiators|Thermostat|Water Pump)$/'                                 => 'CO',
				'/^Fuel/'                                                                                     => 'FU',
				'/^(Gear Oil|Transmission)/'                                                                  => '6MT',
				'/^(Pulleys|Tensioners|V-Belts|Timing Belts)$/'                                               => 'ME',
				'/^Shocks/'                                                                                   => 'FS',
				'/^Wiper/'                                                                                    => 'WW',
				''                                                                                            => 'GN',
			),
		);
	}

	/**
	 * Section code for a diagram, or '' for categories that are not split (Accessories).
	 */
	public static function classify( $top, $raw_name ) {
		$rules = self::rules();
		if ( ! isset( $rules[ $top ] ) ) {
			return '';
		}
		foreach ( $rules[ $top ] as $re => $code ) {
			if ( '' === $re || preg_match( $re, (string) $raw_name ) ) {
				return $code;
			}
		}
		return '';
	}

	/**
	 * Category term name for a section: "IN – Intake (Induction)".
	 */
	public static function term_name( $code ) {
		$all = self::all();
		return $code . ' – ' . ( isset( $all[ $code ] ) ? $all[ $code ] : $code );
	}

	public static function order( $code ) {
		$i = array_search( $code, array_keys( self::all() ), true );
		return false === $i ? 999 : ( $i + 1 ) * 10;
	}
}
