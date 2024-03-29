<?php

/**
 * Object that provides entity lookup table from entity name to character
 */
class HTMLPurifier_EntityLookup {
    
    /**
     * Assoc array of entity name to character represented.
     * @public
     */
    var $table;
    
    /**
     * Sets up the entity lookup table from the serialized file contents.
     * @note The serialized contents are versioned, but were generated
     *       using the maintenance script generate_entity_file.php
     * @warning This is not in constructor to help enforce the Singleton
     */
    function setup($file = false) {
        if (!$file) {
            $file = HTMLPURIFIER_PREFIX . '/HTMLPurifier/EntityLookup/entities.ser';
        }
        $this->table = unserialize(file_get_contents($file));
    }
    
    /**
     * Retrieves sole instance of the object.
     * @static
     * @param Optional prototype of custom lookup table to overload with.
     */
    function instance($prototype = false) {
        // no references, since PHP doesn't copy unless modified
        static $instance = null;
        if ($prototype) {
            $instance = $prototype;
        } elseif (!$instance) {
            $instance = new HTMLPurifier_EntityLookup();
//            $instance->setup();
        }
        return $instance;
    }
    
}

