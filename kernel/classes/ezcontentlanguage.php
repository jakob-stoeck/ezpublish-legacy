<?php
//
// Definition of eZContentLanguage class
//
// Created on: <08-Feb-2006 10:23:51 jk>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ publish
// SOFTWARE RELEASE: 3.10.x
// COPYRIGHT NOTICE: Copyright (C) 1999-2006 eZ systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

include_once( 'kernel/classes/ezpersistentobject.php' );
include_once( 'lib/ezlocale/classes/ezlocale.php' );

define( 'CONTENT_LANGUAGES_MAX_COUNT', 30 );

class eZContentLanguage extends eZPersistentObject
{
    /**
     * Constructor.
     *
     * \param row Parameter passed to the constructor of eZPersistentObject.
     */
    function eZContentLanguage( $row = array() )
    {
        $this->eZPersistentObject( $row );
    }

    /**
     * Persistent object's definition.
     */
    static function definition()
    {
        return array( 'fields' => array( 'id' => array( 'name' => 'ID',
                                                        'datatype' => 'integer',
                                                        'required' => true ),
                                         'name' => array( 'name' => 'Name',
                                                          'datatype' => 'string',
                                                          'required' => true ),
                                         'locale' => array( 'name' => 'Locale',
                                                            'datatype' => 'string',
                                                            'required' => true ),
                                         'disabled' => array( 'name' => 'Disabled',     /* disabled is reserved for the future */
                                                              'datatype' => 'integer',
                                                              'default' => 0,
                                                              'required' => false ) ),
                      'keys' => array( 'id' ),
                      'function_attributes' => array( 'translation' => 'translation',
                                                      'locale_object' => 'localeObject',
                                                      'object_count' => 'objectCount' ),
                      'sort' => array( 'name' => 'asc' ),
                      'class_name' => 'eZContentLanguage',
                      'name' => 'ezcontent_language' );
    }

    /**
     * Adds new language to the site.
     *
     * \param locale Locale code (e.g. 'slk-SK') of language to add.
     * \param name Optional. Name of the language. If not specified, the international language name for the $locale locale
     *             will be used.
     * \return eZContentLanguage object of the added language (or the existing one if specified language has been already used)
     *         or false in case of any error (invalid locale code or already reached CONTENT_LANGUAGES_MAX_COUNT languages).
     * \static
     */
    static function addLanguage( $locale, $name = null )
    {
        $localeObject = eZLocale::instance( $locale );
        if ( !$localeObject )
        {
            eZDebug::writeError( "No such locale $locale!", 'eZContentLanguage::addLanguage' );
            return false;
        }

        if ( $name === null )
        {
            $name = $localeObject->attribute( 'intl_language_name' );
        }

        $db = eZDB::instance();

        $languages = eZContentLanguage::fetchList( true );

        if ( ( $existingLanguage = eZContentLanguage::fetchByLocale( $locale ) ) )
        {
            eZDebug::writeWarning( "Language '$locale' already exists!", 'eZContentLanguage::addLanguage' );
            return $existingLanguage;
        }

        if ( count( $languages ) >= CONTENT_LANGUAGES_MAX_COUNT )
        {
            eZDebug::writeError( 'Too many languages, cannot add more!', 'eZContentLanguage::addLanguage' );
            return false;
        }

        $db->lock( 'ezcontent_language' );

        $idSum = 0;
        foreach( $languages as $language )
        {
            $idSum += $language->attribute( 'id' );
        }

        // ID 1 is reserved
        $candidateId = 2;
        while ( $idSum & $candidateId )
        {
            $candidateId *= 2;
        }

        $newLanguage = new eZContentLanguage( array(
                                                  'id' => $candidateId,
                                                  'locale' => $locale,
                                                  'name' => $name,
                                                  'disabled' => 0 ) );
        $newLanguage->store();

        $db->unlock();

        eZContentLanguage::fetchList( true );

        // clear the cache
        include_once( 'kernel/classes/ezcontentcachemanager.php' );
        eZContentCacheManager::clearAllContentCache();

        return $newLanguage;
    }

    /**
     * Removes the language specified by ID.
     *
     * \param id ID of the language to be removed.
     * \return True if the language was removed from the site, false otherwise.
     * \static
     */
    static function removeLanguage( $id )
    {
        $language = eZContentLanguage::fetch( $id );
        if ( $language )
        {
            return $language->remove();
        }
        else
        {
            return false;
        }
    }

    /**
     * Removes the language if there is no object having translation in it.
     *
     * \return True if the language was removed from the site, false otherwise.
     */
    function remove()
    {
        if ( $this->objectCount() > 0 )
        {
            return false;
        }

        eZPersistentObject::remove();

        include_once( 'kernel/classes/ezcontentcachemanager.php' );
        eZContentCacheManager::clearAllContentCache();

        eZContentLanguage::fetchList( true );

        return true;
    }

    /**
     * Fetches the list of the languages used on the site.
     *
     * \param forceReloading Optional. If true, the list will be fetched from database even if it is cached in memory.
     *                       Default value is false.
     * \return Array of the eZContentLanguage objects of languages used on the site.
     * \static
     */
    static function fetchList( $forceReloading = false )
    {
        if ( !isset( $GLOBALS['eZContentLanguageList'] ) || $forceReloading )
        {
            $mask = 1; // we want have 0-th bit set too!
            $languages = eZPersistentObject::fetchObjectList( eZContentLanguage::definition() );

            unset( $GLOBALS['eZContentLanguageList'] );
            unset( $GLOBALS['eZContentLanguageMask'] );
            $GLOBALS['eZContentLanguageList'] = array();
            foreach ( $languages as $language )
            {
                $GLOBALS['eZContentLanguageList'][$language->attribute( 'id' )] = $language;
                $mask += $language->attribute( 'id' );
            }

            $GLOBALS['eZContentLanguageMask'] = $mask;
        }

        return $GLOBALS['eZContentLanguageList'];
    }

    /**
     * Fetches the array with names and IDs of the languages used on the site. This method is used by the permission system.
     *
     * \param forceReloading Optional. If true, the list will be fetched from database even if it is cached in memory.
     *                       Default value is false.
     * \return Array with names and IDs of the languages used on the site.
     * \static
     */
    static function fetchLimitationList( $forceReloading = false )
    {
        $languages = array();
        foreach ( $this->fetchList( $forceReloading ) as $language )
        {
            $languages[] = array( 'name' => $language->attribute( 'name' ),
                                  'id' => $language->attribute( 'locale' ) );
        }
        return $languages;
    }

   /**
     * Fetches the array of locale codes of the languages used on the site.
     *
     * \return Array of locale codes of the languages used on the site.
     * \static
     */
    static function fetchLocaleList()
    {
        $languages = eZContentLanguage::fetchList();
        $localeList = array();

        foreach ( $languages as $language )
        {
            $localeList[] = $language->attribute( 'locale' );
        }

        return $localeList;
    }

    /**
     * Fetches the language identified by ID.
     *
     * \param id Identifier of the language to fetch.
     * \return eZContentLanguage object of language identified by ID $id.
     * \static
     */
    static function fetch( $id )
    {
        $languages = eZContentLanguage::fetchList();

        return isset( $languages[$id] )? $languages[$id]: false;
    }

    /**
     * Fetches the language identified by locale code.
     *
     * \param locale Locale of the language to fetch, e. g. 'slk-SK'.
     * \return eZContentLanguage object identified by locale code $locale.
     */
    static function fetchByLocale( $locale, $createIfNotExist = false )
    {
        $languages = eZContentLanguage::fetchList();

        foreach ( $languages as $language )
        {
            if ( $language->attribute( 'locale' ) == $locale )
            {
                return $language;
            }
        }

        $language = false;
        if ( $createIfNotExist )
        {
            $language = eZContentLanguage::addLanguage( $locale );
        }

        return $language;
    }

    /**
     * Fetches the list of the prioritized languages (in the correct order).
     *
     * \param languageList Optional. If specified, this array of locale codes with will override the INI
     *                     settings. Usage of this parameter is restricted to methods of this class!
     *                     See eZContentLanguage::setPrioritizedLanguages().
     * \return Array of the eZContentLanguage objects of the prioritized languages.
     * \static
     */
    static function prioritizedLanguages( $languageList = false )
    {
        if ( !isset( $GLOBALS['eZContentLanguagePrioritizedLanguages'] ) )
        {
            $GLOBALS['eZContentLanguagePrioritizedLanguages'] = array();

            $ini = eZINI::instance();

            $languageListAsParameter = false;
            if ( $languageList )
            {
                $languageListAsParameter = true;
            }

            if ( !$languageList && $ini->hasVariable( 'RegionalSettings', 'SiteLanguageList' ) )
            {
                $languageList = $ini->variable( 'RegionalSettings', 'SiteLanguageList' );
            }

            if ( !$languageList )
            {
                $languageList = array( $ini->variable( 'RegionalSettings', 'ContentObjectLocale' ) );
            }

            $processedLocaleCodes = array();
            foreach ( $languageList as $localeCode )
            {
                if ( in_array( $localeCode, $processedLocaleCodes ) )
                {
                    continue;
                }
                $processedLocaleCodes[] = $localeCode;
                $language = eZContentLanguage::fetchByLocale( $localeCode );
                if ( $language )
                {
                    $GLOBALS['eZContentLanguagePrioritizedLanguages'][] = $language;
                }
                else
                {
                    eZDebug::writeWarning( "Language '$localeCode' does not exist or is not used!", 'eZContentLanguage::prioritizedLanguages' );
                }
            }

            if ( ( !$languageListAsParameter && $ini->variable( 'RegionalSettings', 'ShowUntranslatedObjects' ) == 'enabled' ) ||
                 ( isset( $GLOBALS['eZContentLanguageCronjobMode'] ) && $GLOBALS['eZContentLanguageCronjobMode'] ) )
            {
                $completeList = eZContentLanguage::fetchList();
                foreach ( $completeList as $language )
                {
                    if ( !in_array( $language->attribute( 'locale' ), $languageList ) )
                    {
                        $GLOBALS['eZContentLanguagePrioritizedLanguages'][] = $language;
                    }
                }
            }
        }

        return $GLOBALS['eZContentLanguagePrioritizedLanguages'];
    }

    /**
     * Returns the array of the locale codes of the prioritized languages (in the correct order).
     *
     * \return Array of the locale codes of the prioritized languages (in the correct order).
     * \see eZContentLanguage::prioritizedLanguages()
     * \static
     */
    static function prioritizedLanguageCodes()
    {
        $languages = eZContentLanguage::prioritizedLanguages();
        $localeList = array();

        foreach ( $languages as $language )
        {
            $localeList[] = $language->attribute( 'locale' );
        }

        return $localeList;
    }

    /**
     * Overrides the prioritized languages set by INI settings with the specified languages.
     *
     * \param languages Locale codes of the languages which will override the prioritized languages
     *                  (the order is relevant).
     * \static
     */
    static function setPrioritizedLanguages( $languages )
    {
        unset( $GLOBALS['eZContentLanguagePrioritizedLanguages'] );
        eZContentLanguage::prioritizedLanguages( $languages );
    }

    /**
     * Clears the prioritized language list set by eZContentLanguage::setPrioritizedLanguages and reloading
     * the list from INI settings.
     *
     * \static
     */
    static function clearPrioritizedLanguages()
    {
        eZContentLanguage::setPrioritizedLanguages( false );
    }

    /**
     * Returns the most prioritized language.
     *
     * \return eZContentLanguage object for the most prioritized language.
     * \static
     */
    static function topPriorityLanguage()
    {
        $prioritizedLanguages = eZContentLanguage::prioritizedLanguages();
        if ( $prioritizedLanguages )
        {
            return $prioritizedLanguages[0];
        }
        else
        {
            return false;
        }
    }

    /**
     * \return Locale object for this language.
     */
    function localeObject()
    {
        include_once( 'lib/ezlocale/classes/ezlocale.php' );

        $locale = eZLocale::instance( $this->Locale );
        return $locale;
    }

    /**
     * Returns array of languages which have set the corresponding bit in the mask.
     *
     * \param mask Bitmap specifying which languages should be returned.
     * \return Array of eZContentLanguage objects of languages which have set the corresponding bit in $mask.
     */
    static function languagesByMask( $mask )
    {
        $result = array();

        $languages = eZContentLanguage::fetchList();
        foreach ( $languages as $key => $language )
        {
            if ( (int) $key & (int) $mask )
            {
                $result[$language->attribute( 'locale' )] = $language;
            }
        }

        return $result;
    }

    /**
     * Returns array of prioritized languages which have set the corresponding bit in the mask.
     *
     * \param mask Bitmap specifying which languages should be returned.
     * \return Array of eZContentLanguage objects of prioritized languages which have set the corresponding bit in $mask.
     */
    static function prioritizedLanguagesByMask( $mask )
    {
        $result = array();

        $languages = eZContentLanguage::prioritizedLanguages();
        foreach ( $languages as $language )
        {
            if ( ( (int) $language->attribute( 'id' ) & (int) $mask ) > 0 )
            {
                $result[$language->attribute( 'locale' )] = $language;
            }
        }

        return $result;
    }

    /**
     * Returns array of prioritized languages which are listed in \a $languageLocaleList.
     * The function does the same as 'prioritizedLanguagesByMask' but uses language locale list instead of language mask.
     *
     * \param languageLocaleList List of language locales to choose from.
     * \return Array of eZContentLanguage objects of prioritized languages which have set the corresponding bit in $mask.
     */
    static function prioritizedLanguagesByLocaleList( $languageLocaleList )
    {
        $result = array();

        if ( is_array( $languageLocaleList ) && count( $languageLocaleList ) > 0 )
        {
            $languages = eZContentLanguage::prioritizedLanguages();
            foreach ( $languages as $language )
            {
                if ( in_array( $language->attribute( 'locale' ), $languageLocaleList ) )
                {
                    $result[$language->attribute( 'locale' )] = $language;
                }
            }
        }

        return $result;
    }

    /**
     * Returns the most prioritized language which has set the corresponding bit in the mask.
     *
     * \param mask Bitmap specifying which languages should be checked.
     * \return eZContentLanguage object of the most prioritized language which have set the corresponding bit in $mask.
     */
    static function topPriorityLanguageByMask( $mask )
    {
        $languages = eZContentLanguage::prioritizedLanguages();
        foreach ( $languages as $language )
        {
            if ( ( (int) $language->attribute( 'id' ) & (int) $mask ) > 0 )
            {
                return $language;
            }
        }
        return false;
    }

    /**
     * Returns the most prioritized language from specified by \a $languageLocaleList list of language locales.
     * The function does the same as 'topPriorityLanguageByMask' but uses language locale list instead of language mask.
     *
     * \param languageLocaleList List of language locales to choose from.
     * \return eZContentLanguage object of the most prioritized language.
     */
    static function topPriorityLanguageByLocaleList( $languageLocaleList )
    {
        if ( is_array( $languageLocaleList ) && count( $languageLocaleList ) > 0 )
        {
            $languages = eZContentLanguage::prioritizedLanguages();
            foreach ( $languages as $language )
            {
                if ( in_array( $language->attribute( 'locale' ), $languageLocaleList ) )
                {
                    return $language;
                }
            }
        }

        return false;
    }

    /**
     * Returns bitmap mask for the specified languages.
     *
     * \param locales Array of strings or a string specifying locale codes of the languages, e. g. 'slk-SK' or array( 'eng-GB', 'nor-NO' )
     * \param setZerothBit Optional. Specifies if the 0-th bit of mask should be set. False by default.
     * \return Bitmap mask having set the corresponding bits for the specified languages.
     */
    static function maskByLocale( $locales, $setZerothBit = false )
    {
        if ( !$locales )
        {
            return 0;
        }

        if ( !is_array( $locales ) )
        {
            $locales = array( $locales );
        }

        $mask = 0;
        if ( $setZerothBit )
        {
            $mask = 1;
        }

        foreach( $locales as $locale )
        {
            $language = eZContentLanguage::fetchByLocale( $locale );
            if ( $language )
            {
                $mask += $language->attribute( 'id' );
            }
        }

        return (int) $mask;
    }

    /**
     * \static
     * Returns id of the language specified.
     *
     * \param locale String specifying locale code of the language, e. g. 'slk-SK'
     * \return ID of the language specified by locale or false if the language is not set on the site.
     */
    static function idByLocale( $locale )
    {
        $language = eZContentLanguage::fetchByLocale( $locale );
        if ( $language )
        {
            return (int)$language->attribute( 'id' );
        }
        else
        {
            return false;
        }
    }

    /**
     * Returns the SQL where-condition for selecting the rows (objects, object versions) which exist in any
     * of prioritized languages or are always available.
     *
     * \param languageListTable Name of the table
     * \param languageListAttributeName Optional. Name of the attribute in the table which contains the bitmap mask. 'language_mask' by default.
     * \return SQL where-condition described above.
     * \static
     */
    static function languagesSQLFilter( $languageListTable, $languageListAttributeName = 'language_mask' )
    {
        $prioritizedLanguages = eZContentLanguage::prioritizedLanguages();
        $mask = 1; // 1 - always available objects
        foreach( $prioritizedLanguages as $language )
        {
            $mask += $language->attribute( 'id' );
        }

        $db = eZDB::instance();
        if ( $db->databaseName() == 'oracle' )
        {
            return "\n bitand( $languageListTable.$languageListAttributeName, $mask ) > 0 \n";
        }
        else
        {
            return "\n $languageListTable.$languageListAttributeName & $mask > 0 \n";
        }
    }

    /**
     * Returns the SQL where-condition for selecting the rows (with object names, attributes etc.) in the correct language,
     * i. e. in the most prioritized language from those in which an object exists.
     *
     * \param languageTable Name of the table containing the attribute with bitmaps.
     * \param languageListTable Name of the table containing the attribute with language id.
     * \param languageAttributeName Optional. Name of the attribute in $languageTable which contains
     *                               the language id. 'language_id' by default.
     * \param languageListAttributeName Optional. Name of the attribute in $languageListTable which contains
     *                                  the bitmap mask. 'language_mask' by default.
     * \return SQL where-condition described above.
     */
    static function sqlFilter( $languageTable, $languageListTable = null, $languageAttributeName = 'language_id', $languageListAttributeName = 'language_mask' )
    {
        $db = eZDB::instance();

        if ( $languageListTable === null )
        {
            $languageListTable = $languageTable;
        }

        $prioritizedLanguages = eZContentLanguage::prioritizedLanguages();
        if ( $db->databaseName() == 'oracle' )
        {
            $leftSide = "bitand( $languageListTable.$languageListAttributeName - bitand( $languageListTable.$languageListAttributeName, $languageTable.$languageAttributeName ), 1 )\n";
            $rightSide = "bitand( $languageTable.$languageAttributeName, 1 )\n";
        }
        else
        {
            $leftSide = "    ( (   $languageListTable.$languageListAttributeName - ( $languageListTable.$languageListAttributeName & $languageTable.$languageAttributeName ) ) & 1 )\n";
            $rightSide = "  ( $languageTable.$languageAttributeName & 1 )\n";
        }

        for ( $index = count( $prioritizedLanguages ) - 1, $multiplier = 2; $index >= 0; $index--, $multiplier *= 2 )
        {
            $id = $prioritizedLanguages[$index]->attribute( 'id' );

            if ( $db->databaseName() == 'oracle' )
            {
                $leftSide .= "   + bitand( $languageListTable.$languageListAttributeName - bitand( $languageListTable.$languageListAttributeName, $languageTable.$languageAttributeName ), $id )";
                $rightSide .= "   + bitand( $languageTable.$languageAttributeName, $id )";
            }
            else
            {
                $leftSide .= "   + ( ( ( $languageListTable.$languageListAttributeName - ( $languageListTable.$languageListAttributeName & $languageTable.$languageAttributeName ) ) & $id )";
                $rightSide .= "   + ( ( $languageTable.$languageAttributeName & $id )";
            }

            if ( $multiplier > $id )
            {
                $factor = $multiplier / $id;
                if ( $db->databaseName() == 'oracle' )
                {
                    $factorTerm = ' * ' . $factor;
                }
                else
                {
                    for ( $shift = 0; $factor > 1; $factor = $factor / 2, $shift++ ) ;
                    $factorTerm = ' << '. $shift;
                }
                $leftSide .= $factorTerm;
                $rightSide .= $factorTerm;
            }
            else if ( $multiplier < $id )
            {
                $factor = $id / $multiplier;
                if ( $db->databaseName() == 'oracle' )
                {
                    $factorTerm = ' / ' . $factor;
                }
                else
                {
                    for ( $shift = 0; $factor > 1; $factor = $factor / 2, $shift++ ) ;
                    $factorTerm = ' >> '. $shift;
                }
                $leftSide .= $factorTerm;
                $rightSide .= $factorTerm;
            }
            if ( $db->databaseName() != 'oracle' )
            {
                $leftSide .= " )\n";
                $rightSide .= " )\n";
            }
        }

        if ( $db->databaseName() == 'oracle' )
        {
            $sql = "bitand( $languageTable.$languageAttributeName, $languageListTable.$languageListAttributeName ) > 0";
        }
        else
        {
            $sql = "$languageTable.$languageAttributeName & $languageListTable.$languageListAttributeName > 0";
        }

        return "\n ( $sql AND\n $leftSide   <\n   $rightSide ) \n";
    }

    /**
     * \return The count of objects containing the translation in this language.
     */
    function &objectCount()
    {
        $db = eZDB::instance();

        $languageID = $this->ID;
        if ( $db->databaseName() == 'oracle' )
        {
            $whereSQL = "bitand( language_mask, $languageID ) > 0";
        }
        else
        {
            $whereSQL = "language_mask & $languageID > 0";
        }

        $count = $db->arrayQuery( "SELECT COUNT(*) AS count FROM ezcontentobject WHERE $whereSQL" );
        $count = $count[0]['count'];

        return $count;
    }

    /**
     * \return The count of objects having this language as the initial/main one.
     */
    function objectInitialCount()
    {
        $db = eZDB::instance();

        $languageID = $this->ID;
        $count = $db->arrayQuery( "SELECT COUNT(*) AS count FROM ezcontentobject WHERE initial_language_id = '$languageID'" );
        $count = $count[0]['count'];

        return $count;
    }

    /**
     * \return Reference to itself. Kept because of the backward compatibility.
     */
    function &translation()
    {
        return $this;
    }

    /**
     * \deprecated
     */
    function updateObjectNames()
    {
    }

    /**
     * Switches on the cronjob mode. In this mode, the languages which are not in the list of the prioritized languages
     * will be automatically added to it.
     *
     * \param enable Optional. If false, it will switch off the cronjob mode. True by default.
     */
    static function setCronjobMode( $enable = true )
    {
        $GLOBALS['eZContentLanguageCronjobMode'] = true;
        unset( $GLOBALS['eZContentLanguagePrioritizedLanguages'] );
    }

    /**
     * Switches off the cronjob mode.
     *
     * \see eZContentLanguage::setCronjobMode()
     */
    static function clearCronjobMode()
    {
        eZContentLanguage::setCronjobMode( false );
    }

    /**
     * Returns the Javascript array with locale codes and names of the languages which have set the corresponding
     * bit in specified mask.
     *
     * \param mask Bitmap mask specifying which languages should be considered.
     * \return JavaScript array described above.
     */
    static function jsArrayByMask( $mask )
    {
        $jsArray = array();
        $languages = eZContentLanguage::prioritizedLanguagesByMask( $mask );
        foreach ( $languages as $key => $language )
        {
            $jsArray[] = "{ locale: '".$language->attribute( 'locale' ).
                "', name: '".$language->attribute( 'name' )."' }";
        }

        if ( $jsArray )
        {
            return '[ '.implode( ', ', $jsArray ).' ]';
        }
        else
        {
            return false;
        }
    }

    /**
     * \return The bitmap mask containing all languages, i. e. the sum of the IDs of all languages. (The 0-th bit is set.)
     */
    static function maskForRealLanguages()
    {
        if ( !isset( $GLOBALS['eZContentLanguageMask'] ) )
        {
            eZContentLanguage::fetchList( true );
        }
        return $GLOBALS['eZContentLanguageMask'];
    }

    /**
     * Removes all memory cache forcing it to read from database again for next method calls.
     *
     * \static
     */
    static function expireCache()
    {
        unset( $GLOBALS['eZContentLanguageList'],
               $GLOBALS['eZContentLanguagePrioritizedLanguages'],
               $GLOBALS['eZContentLanguageMask'],
               $GLOBALS['eZContentLanguageCronjobMode'] );
    }
}

?>
