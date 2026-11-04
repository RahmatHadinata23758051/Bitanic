<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported Sensor Models and Specifications
    |--------------------------------------------------------------------------
    |
    | Each supported sensor specification documents physical register mapping,
    | parameter units, measurable physical ranges, approved calibration methods,
    | and firmware compatibility.
    | Universal unverified mathematical curves or unverified hardware offsets
    | are strictly prohibited.
    |
    */

    'models' => [
        'RS485-SOIL-7IN1' => [
            'name' => 'Modbus RS485 7-in-1 Soil Multi-Parameter Sensor',
            'protocol' => 'Modbus RTU over RS485',
            'baud_rate' => 9600,
            'data_bits' => 8,
            'stop_bits' => 1,
            'parity' => 'none',
            'firmware_compatibility' => 'ESP32-BTLP-v1.0+',
            'downlink_supported' => false, // Physical EEPROM write downlink is BLOCKED pending firmware review
            'downlink_status' => 'BLOCKED',
            'parameters' => [
                'ph' => [
                    'name' => 'Soil pH',
                    'unit' => 'pH',
                    'register_address' => '0x0006',
                    'register_type' => 'holding_register',
                    'data_format' => 'uint16',
                    'scale_factor' => 0.1,
                    'min_range' => 3.0,
                    'max_range' => 10.0,
                    'supported_methods' => ['offset', 'two_point_vector'],
                    'default_offset_bounds' => [-2.0, 2.0],
                ],
                'humidity' => [
                    'name' => 'Soil Moisture',
                    'unit' => '%',
                    'register_address' => '0x0002',
                    'register_type' => 'holding_register',
                    'data_format' => 'uint16',
                    'scale_factor' => 0.1,
                    'min_range' => 0.0,
                    'max_range' => 100.0,
                    'supported_methods' => ['offset', 'two_point_vector'],
                    'default_offset_bounds' => [-20.0, 20.0],
                ],
                'ec' => [
                    'name' => 'Electrical Conductivity',
                    'unit' => 'µS/cm',
                    'register_address' => '0x0005',
                    'register_type' => 'holding_register',
                    'data_format' => 'uint16',
                    'scale_factor' => 1.0,
                    'min_range' => 0.0,
                    'max_range' => 20000.0,
                    'supported_methods' => ['offset', 'two_point_vector'],
                    'default_offset_bounds' => [-500.0, 500.0],
                ],
                'temperature' => [
                    'name' => 'Soil Temperature',
                    'unit' => '°C',
                    'register_address' => '0x0003',
                    'register_type' => 'holding_register',
                    'data_format' => 'int16',
                    'scale_factor' => 0.1,
                    'min_range' => -40.0,
                    'max_range' => 80.0,
                    'supported_methods' => ['offset', 'two_point_vector'],
                    'default_offset_bounds' => [-10.0, 10.0],
                ],
                'nitrogen' => [
                    'name' => 'Soil Nitrogen (N)',
                    'unit' => 'mg/kg',
                    'register_address' => '0x0000',
                    'register_type' => 'holding_register',
                    'data_format' => 'uint16',
                    'scale_factor' => 1.0,
                    'min_range' => 0.0,
                    'max_range' => 1999.0,
                    'supported_methods' => ['offset'],
                    'default_offset_bounds' => [-100.0, 100.0],
                ],
                'phosphorus' => [
                    'name' => 'Soil Phosphorus (P)',
                    'unit' => 'mg/kg',
                    'register_address' => '0x0001',
                    'register_type' => 'holding_register',
                    'data_format' => 'uint16',
                    'scale_factor' => 1.0,
                    'min_range' => 0.0,
                    'max_range' => 1999.0,
                    'supported_methods' => ['offset'],
                    'default_offset_bounds' => [-100.0, 100.0],
                ],
                'potassium' => [
                    'name' => 'Soil Potassium (K)',
                    'unit' => 'mg/kg',
                    'register_address' => '0x0004',
                    'register_type' => 'holding_register',
                    'data_format' => 'uint16',
                    'scale_factor' => 1.0,
                    'min_range' => 0.0,
                    'max_range' => 1999.0,
                    'supported_methods' => ['offset'],
                    'default_offset_bounds' => [-100.0, 100.0],
                ],
            ],
        ],
    ],
];
