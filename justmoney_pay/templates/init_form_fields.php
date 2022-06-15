<?php

function getJustMoneyPayFormFields() {

	return [
		'enabled'     => [
			'title'   => __( 'Enable/Disable', 'woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable JustMoney Pay', 'woocommerce' ),
			'default' => 'yes'
		],

		'seller_wallet'   => [
			'title'       => __( 'EVM Wallet address', 'woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Please enter your EVM wallet address to receive the funds to', 'woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
			'placeholder' => ''
		],
		'seller_wallet_tron'   => [
            'title'       => __( 'Tron Wallet address', 'woocommerce' ),
            'type'        => 'text',
            'description' => __( 'Please enter your Tron wallet address to receive the funds to', 'woocommerce' ),
            'default'     => '',
            'desc_tip'    => true,
            'placeholder' => ''
        ],
		'debug'       => [
			'title'       => __( 'Debug Log', 'woocommerce' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable logging', 'woocommerce' ),
			'default'     => 'no',
			'desc_tip'    => true,
			'description' => sprintf( __( 'Log JustMoney Pay events', 'woocommerce' ), wc_get_log_file_path( 'justmoneypay' ) )
		],
	];
}
