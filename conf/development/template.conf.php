<?php
/** \file
 *  \brief      Configurations for Springy\Template class.
 *  \copyright  Copyright (c) 2007-2016 FVAL Consultoria e Informática Ltda.\n
 *  \warning    Este arquivo é parte integrante do framework e não pode ser omitido.
 */

/**
 *  \defgroup templatecfg_development Configurações da classe de templates para o ambiente 'development'
 *  \ingroup templatecfg.
 *
 *  As entradas colocadas nesse arquivo serão aplicadas apenas ao ambiente 'development'.
 *
 *  Seu sistema pode não possuir esse ambiente, então use-o como modelo para criação do arquivo de
 *  parâmetros de configuração para os ambientes que seu sistema possua.
 *
 *  Veja \link templatecfg Configurações da classe de templates \endlink para entender as entradas de configuração possíveis.
 *
 *  \see templatecfg
 */
/**@{*/

/// Configurações para o ambiente de Desenvolvimento
$conf = [
    'debug'          => true,
    'debugging_ctrl' => 'URL',
    'auto_reload'    => false,
];

/**@}*/
