<?php
//
// Definition of eZTemplate class
//
// Created on: <01-Mar-2002 13:49:57 amos>
//
// Copyright (C) 1999-2002 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE.GPL included in
// the packaging of this file.
//
// Licencees holding valid "eZ publish professional licences" may use this
// file in accordance with the "eZ publish professional licence" Agreement
// provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" is available at
// http://ez.no/home/licences/professional/. For pricing of this licence
// please contact us via e-mail to licence@ez.no. Further contact
// information is available at http://ez.no/home/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/*! \file eztemplate.php
 Template system manager.
*/

/*! \defgroup eZTemplate Template system */

/*!
  \class eZTemplate eztemplate.php
  \ingroup eZTemplate
  \brief The main manager for templates

  The template systems allows for separation of code and
  layout by moving the layout part into template files. These
  template files are parsed and processed with template variables set
  by the PHP code.

  The template system in itself is does not do much, it parses template files
  according to a rule set sets up a tree hierarchy and process the data
  using functions and operators. The standard template system comes with only
  a few functions and no operators, it is meant for these functions and operators
  to be specified by the users of the template system. But for simplicity a few
  help classes is available which can be easily enabled.

  The classes are:
  - eZTemplateDelimitFunction - Inserts the left and right delimiter which are normally parsed.
  - eZTemplateSectionFunction - Allows for conditional blocks and loops.
  - eZTemplateIncludeFunction - Includes external templates
  - eZTemplateSequenceFunction - Creates sequences arrays
  - eZTemplateSwitchFunction - Conditional output of template

  - eZTemplatePHPOperator - Allows for easy redirection of operator names to PHP functions.
  - eZTemplateLocaleOperator - Allows for locale conversions.
  - eZTemplateArrayOperator - Creates arrays
  - eZTemplateAttributeOperator - Displays contents of template variables, useful for debugging
  - eZTemplateImageOperator - Converts text to image
  - eZTemplateLogicOperator - Various logical operators for boolean handling
  - eZTemplateUnitOperator - Unit conversion and display

  To enable these functions and operator use registerFunction and registerOperator.

  In keeping with the spirit of being simple the template system does not know how
  to get the template files itself. Instead it relies on resource handlers, these
  handlers fetches the template files using different kind of transport mechanism.
  For simplicity a default resource class is available, eZTemplateFileResource fetches
  templates from the filesystem.

  The parser process consists of three passes, each pass adds a new level of complexity.
  The first pass strips text from template blocks which starts with a left delimiter and
  ends with a right delimiter (default is { and } ), and places them in an array.
  The second pass iterates the text and block elements and removes newlines from
  text before function blocks and text after function blocks.
  The third pass builds the tree according the function rules.

  Processing is done by iterating over the root of the tree, if a text block is found
  the text is appended to the result text. If a variable or contant is it's data is extracted
  and any operators found are run on it before fetching the result and appending it to
  the result text. If a function is found the function is called with the parameters
  and it's up to the function handle children if any.

  Constants and template variables will usually be called variables since there's little
  difference. A template variable expression will start with a $ and consists of a
  namespace (optional) a name and attribues(optional). The variable expression
  \verbatim $root:var.attr1 \endverbatim exists in the "root" namespace, has the name "var" and uses the
  attribute "attr1". Some functions will create variables on demand, to avoid name conflicts
  namespaces were introduced, each function will place the new variables in a namespace
  specified in the template file. Attribues are used for fetching parts of the variable,
  for instance an element in an array or data in an object. Since the syntax is the
  same for arrays and objects the PHP code can use simple arrays when speed is required,
  the template code will not care.
  A different syntax is also available when you want to access an attribute using a variable.
  For instance \verbatim $root:var[$attr_var] \endverbatim, if the variable $attr_var contains "attr1" it would
  access the same attribute as in the first example.

  The syntax for operators is a | and a name, optionally parameters can be specified with
  ( and ) delimited with ,. Valid operators are \verbatim |upcase, |l10n(date) \endverbatim.

  Functions look a lot like HTML/XML tags. The function consists of a name and parameters
  which are assigned using the param=value syntax. Some parameters may be required while
  others may be optionally, the exact behaviour is specified by each function.
  Valid functions are \verbatim "section name=abc loop=4" \endverbatim

  Example of usage:
\code
// Init template
$tpl =& eZTemplate::instance();

$tpl->registerOperators( new eZTemplatePHPOperator( array( "upcase" => "strtoupper",
                                                           "reverse" => "strrev" ) ) );
$tpl->registerOperators( new eZTemplateLocaleOperator() );
$tpl->registerFunction( "section", new eZTemplateSectionFunction( "section" ) );
$tpl->registerFunctions( new eZTemplateDelimitFunction() );

$tpl->setVariable( "my_var", "{this value set by variable}", "test" );
$tpl->setVariable( "my_arr", array( "1st", "2nd", "third", "fjerde" ) );
$tpl->setVariable( "multidim", array( array( "a", "b" ),
                                      array( "c", "d" ),
                                      array( "e", "f" ),
                                      array( "g", "h" ) ) );

class mytest
{
    function mytest( $n, $s )
    {
        $this->n = $n;
        $this->s = $s;
    }

    function hasAttribute( $attr )
    {
        return ( $attr == "name" || $attr == "size" );
    }

    function &attribute( $attr )
    {
        switch ( $attr )
        {
            case "name";
            return $this->n;
            case "size";
            return $this->s;
            default:
                return null;
        }
    }

};

$tpl->setVariable( "multidim_obj", array( new mytest( "jan", 200 ),
                                          new mytest( "feb", 200 ),
                                          new mytest( "john", 200 ),
                                          new mytest( "doe", 50 ) ) );
$tpl->setVariable( "curdate", mktime() );

$tpl->display( "lib/eztemplate/example/test.tpl" );

// test.tpl

{section name=outer loop=4}
123
{delimit}::{/delimit}
{/section}

{literal test=1} This is some {blah arg1="" arg2="abc" /} {/literal}

<title>This is a test</title>
<table border="1">
<tr><th>{$test:my_var}
{"some text!!!"|upcase|reverse}</th></tr>
{section name=abc loop=$my_arr}
<tr><td>{$abc:item}</td></tr>
{/section}
</table>

<table border="1">
{section name=outer loop=$multidim}
<tr>
{section name=inner loop=$outer:item}
<td>{$inner:item}</td>
{/section}
</tr>
{/section}
</table>

<table border="1">
{section name=outer loop=$multidim_obj}
<tr>
<td>{$outer:item.name}</td>
<td>{$outer:item.size}</td>
</tr>
{/section}
</table>

{section name=outer loop=$nonexistingvar}
<b><i>Dette skal ikke vises</b></i>
{section-else}
<b><i>This is shown when the {ldelim}$loop{rdelim} variable is non-existant</b></i>
{/section}


Denne koster {1.4|l10n(currency)}<br>
{-123456789|l10n(number)}<br>
{$curdate|l10n(date)}<br>
{$curdate|l10n(shortdate)}<br>
{$curdate|l10n(time)}<br>
{$curdate|l10n(shorttime)}<br>
{include file="test2.tpl"/}

\endcode
*/

include_once( "lib/ezutils/classes/ezdebug.php" );

include_once( "lib/eztemplate/classes/eztemplatefileresource.php" );

include_once( "lib/eztemplate/classes/eztemplateroot.php" );
include_once( "lib/eztemplate/classes/eztemplatetextelement.php" );
include_once( "lib/eztemplate/classes/eztemplatevariableelement.php" );
include_once( "lib/eztemplate/classes/eztemplateoperatorelement.php" );
include_once( "lib/eztemplate/classes/eztemplatefunctionelement.php" );

define( "EZ_RESOURCE_FETCH", 1 );
define( "EZ_RESOURCE_QUERY", 2 );

define( "EZ_ELEMENT_TEXT", 1 );
define( "EZ_ELEMENT_SINGLE_TAG", 2 );
define( "EZ_ELEMENT_NORMAL_TAG", 3 );
define( "EZ_ELEMENT_END_TAG", 4 );
define( "EZ_ELEMENT_VARIABLE", 5 );
define( "EZ_ELEMENT_COMMENT", 6 );

define( "EZ_TEMPLATE_DEBUG_INTERNALS", false );

class eZTemplate
{
    /*!
     Intializes the template with left and right delimiters being { and },
     and a file resource. The literal tag "literal" is also registered.
    */
    function eZTemplate()
    {
        $this->Tree = new eZTemplateRoot();
        $this->LDelim = "{";
        $this->RDelim = "}";

        $this->IncludeText = array();
        $this->IncludeOutput = array();

        $this->registerLiteral( "literal" );

        $res = new eZTemplateFileResource();
        $this->DefaultResource =& $res;
        $this->registerResource( $res );

        $this->Resources = array();
        $this->Text = null;

        $this->AutoloadPathList = array( 'lib/eztemplate/classes/' );
//         $IncludeText ;
//     var $IncludeOutput;
//     var $TimeStamp;
//     var $LDelim;
//     var $RDelim;

//     var $Tree;
        $this->Variables = array();
//     var $Operators;
        $this->Functions = array();
        $this->FunctionAttributes = array();
//     var $Literals;
//     var $ShowDetails = false;
    }

    /*!
     Returns the left delimiter being used.
    */
    function &leftDelimiter()
    {
        return $this->LDelim;
    }

    /*!
     Returns the right delimiter being used.
    */
    function &rightDelimiter()
    {
        return $this->RDelim;
    }

    /*!
     Sets the left delimiter.
    */
    function setLeftDelimiter( $delim )
    {
        $this->LDelim = $delim;
    }

    /*!
     Sets the right delimiter.
    */
    function setRightDelimiter( $delim )
    {
        $this->RDelim = $delim;
    }

    /*!
     Loads the template using the URI $uri and parses it.
    */
    function load( $uri, $extraParameters = false )
    {
        $res =& $this->loadURI( $uri, true, $extraParameters );
        $this->Text = "";
        if ( $res )
        {
            $this->Text =& $res["text"];
            $this->TimeStamp =& $res["time-stamp"];
            $this->Tree->clear();
            $this->parse( $this->Text, $this->Tree, "", $res["resource"], $res["template_name"] );
        }
    }

    /*!
     Loads the template using the URI $uri and returns a structure with the text and timestamp,
     false otherwise.
     The structure keys are:
     - "text", the text.
     - "time-stamp", the timestamp.
    */
    function &loadURI( $uri, $displayErrors = true, &$extraParameters )
    {
        $res = "";
        $template = "";
        $resobj =& $this->resourceFor( $uri, $res, $template );
        if ( !is_object( $resobj ) )
        {
            if ( $displayErrors )
                $this->warning( "", "No resource for \"$res\" and no default resource, aborting." );
            return;
        }
        $text = "";
        $tstamp = null;
        $result = false;
        if ( $resobj->handleResource( $this, $text, $tstamp, $template, EZ_RESOURCE_FETCH, $extraParameters ) )
        {
            $result = array();
            $result["text"] =& $text;
            $result["time-stamp"] =& $tstamp;
            $result["resource"] =& $res;
            $result["template_name"] =& $template;
        }
        else if ( $displayErrors )
            $this->warning( "", "No template could be loaded for \"$template\" using resource \"$res\"" );
        return $result;
    }

    /*!
     Tries to find a cached version of the uri \a $uri in template system.
     If a cached template root exists for that uri there's no need to reload it
     and reparse it.
     \return the root or null if no root is cached.
     \sa setCachedTemplateTree
    */
    function &cachedTemplateTree( $uri )
    {
        $res = "";
        $template = "";
        $root = null;
        $resobj =& $this->resourceFor( $uri, $res, $template );
        if ( !is_object( $resobj ) )
        {
            if ( $displayErrors )
                $this->warning( "", "No resource for \"$res\" and no default resource, aborting." );
            return $root;
        }
        if ( isset( $this->TemplateTrees[$uri] ) )
            $root = $this->TemplateTrees[$uri];
        return $root;
    }

    /*!
     Returns the resource object for URI $uri. If a resource type is specified
     in the URI it is extracted and set in $res. The template name is set in $template
     without any resource specifier. To specify a resource the name and a ":" is
     prepended to the URI, for instance file:my.tpl.
     If no resource type is found the URI the default resource handler is used.
    */
    function &resourceFor( &$uri, &$res, &$template )
    {
        $args =& explode( ":", $uri );
        if ( count( $args ) > 1 )
        {
            $res = $args[0];
            $template = $args[1];
        }
        else
            $template = $uri;
        if ( eZTemplate::isDebugEnabled() )
            eZDebug::writeNotice( "eZTemplate: Loading template \"$template\" with resource \"$res\"" );
        $resobj =& $this->DefaultResource;
        if ( isset( $this->Resources[$res] ) and is_object( $this->Resources[$res] ) )
        {
            $resobj =& $this->Resources[$res];
        }
        return $resobj;
    }

    function setRelation( &$element, $relatedResource, $relatedTemplateName )
    {
        $this->setResourceRelation( $element, $relatedResource );
        $this->setTemplateNameRelation( $element, $relatedTemplateName );
    }

    function setResourceRelation( &$element, $relatedResource )
    {
        if ( method_exists( $element, "setResourceRelation" ) )
            $element->setResourceRelation( $relatedResource );
//         $relation_list =& $this->RelatedResources[$relatedResource];
//         if ( !is_array( $relation_list ) )
//             $relation_list = array();
//         $relation_list[] =& $element;
    }

    function setTemplateNameRelation( &$element, $relatedTemplateName )
    {
        if ( method_exists( $element, "setTemplateNameRelation" ) )
            $element->setTemplateNameRelation( $relatedTemplateName );
//         $relation_list =& $this->RelatedNames[$relatedTemplateName];
//         if ( !is_array( $relation_list ) )
//             $relation_list = array();
//         $relation_list[] =& $element;
    }

    function hasChildren( &$function, $functionName )
    {
        $hasChildren = $function->hasChildren();
        if ( is_array( $hasChildren ) )
            return $hasChildren[$functionName];
        else
            return $hasChildren;
     }

    /*!
     Parses the template file $txt. See the description of this class
     for more information on the parsing process.

     \todo Use indexes in pass 1 and 2 instead of substrings, this means that strings are not extracted
     until they are needed.
    */
    function parse( &$txt, &$root, $nspace, $relatedResource, $relatedTemplateName )
    {
        $this->setRelation( $root, $relatedResource, $relatedTemplateName );
        $this->CurrentRelatedResource = $relatedResource;
        $this->CurrentRelatedTemplateName = $relatedTemplateName;
        $currentRoot =& $root;
        $ldel = $this->LDelim;
        $rdel = $this->RDelim;
        $cnt = strlen( $txt );
        $pos = 0;

        if ( $this->ShowDetails )
            eZDebug::addTimingPoint( "Parse pass 1 (simple tag parsing)" );
        $elements = array();
        while( $pos < $cnt )
        {
            $tagPos = strpos( $txt, $ldel, $pos );
            if ( $tagPos === false )
            {
                // No more tags
                unset( $data );
                $data =& substr( $txt, $pos );
                $elements[] = array( "Text" => $data,
                                     "Type" => EZ_ELEMENT_TEXT );
                $pos = $cnt;
            }
            else
            {
                $blockStart = $tagPos;
                $tagPos++;
                if ( $tagPos < $cnt and
                     $txt[$tagPos] == "*" ) // Comment
                {
                    $endPos = strpos( $txt, "*$rdel", $tagPos + 1 );
                    $len = $endPos - $tagPos;
                    if ( $pos < $blockStart )
                    {
                        // Add text before tag.
                        unset( $data );
                        $data =& substr( $txt, $pos, $blockStart - $pos );
                        $elements[] = array( "Text" => $data,
                                             "Type" => EZ_ELEMENT_TEXT );
                    }
                    if ( $endPos === false )
                    {
                        $endPos = $cnt;
                        $blockEnd = $cnt;
                    }
                    else
                    {
                        $blockEnd = $endPos + 2;
                    }
                    $comment_text = substr( $txt, $tagPos + 1, $endPos - $tagPos - 1 );
                    $elements[] = array( "Text" => $comment_text,
                                         "Type" => EZ_ELEMENT_COMMENT );
                    if ( $pos < $blockEnd )
                        $pos = $blockEnd;
//                     eZDebug::writeDebug( "eZTemplate: Comment: $comment" );
                }
                else
                {
                    $tmp_pos = $tagPos;
                    while( ( $endPos = strpos( $txt, $rdel, $tmp_pos ) ) !== false )
                    {
                        if ( $txt[$endPos-1] != "\\" )
                            break;
                        $tmp_pos = $endPos + 1;
                    }
                    if ( $endPos === false )
                    {
                        // Unterminated tag
                        $this->warning( "parse()", "Unterminated tag at pos $tagPos" );
                        unset( $data );
                        $data =& substr( $txt, $pos );
                        $elements[] = array( "Text" => $data,
                                             "Type" => EZ_ELEMENT_TEXT );
                        $pos = $cnt;
                    }
                    else
                    {
                        $blockEnd = $endPos + 1;
                        $len = $endPos - $tagPos;
                        if ( $pos < $blockStart )
                        {
                            // Add text before tag.
                            unset( $data );
                            $data =& substr( $txt, $pos, $blockStart - $pos );
                            $elements[] = array( "Text" => $data,
                                                 "Type" => EZ_ELEMENT_TEXT );
                        }

                        unset( $tag );
                        $tag = substr( $txt, $tagPos, $len );
                        $tag = preg_replace( "/\\\\[}]/", "}", $tag );
                        $isEndTag = false;
                        $isSingleTag = false;

                        if ( $tag[0] == "/" )
                        {
                            $isEndTag = true;
                            $tag = substr( $tag, 1 );
                        }
                        else if ( $tag[strlen($tag) - 1] == "/" )
                        {
                            $isSingleTag = true;
                            $tag = substr( $tag, 0, strlen( $tag ) - 1 );
                        }

                        if ( $tag[0] == "$" or
                             $tag[0] == "\"" or
                             $tag[0] == "'" or
                             is_numeric( $tag[0] ) or
                             ( $tag[0] == '-' and
                               isset( $tag[1] ) and
                               is_numeric( $tag[1] ) ) or
                             preg_match( "/^[a-z0-9]+\(/", $tag ) )
                        {
                            $elements[] = array( "Text" => $tag,
                                                 "Type" => EZ_ELEMENT_VARIABLE );
                        }
                        else
                        {
                            $type = EZ_ELEMENT_NORMAL_TAG;
                            if ( $isEndTag )
                                $type = EZ_ELEMENT_END_TAG;
                            else if ( $isSingleTag )
                                $type = EZ_ELEMENT_SINGLE_TAG;
                            $spacepos = strpos( $tag, " " );
                            if ( $spacepos === false )
                                $name = $tag;
                            else
                                $name = substr( $tag, 0, $spacepos );
                            $elements[] = array( "Text" => $tag,
                                                 "Name" => $name,
                                                 "Type" => $type );
                        }

                        if ( $pos < $blockEnd )
                            $pos = $blockEnd;
                    }
                }
            }
        }
        if ( $this->ShowDetails )
            eZDebug::addTimingPoint( "Parse pass 2 (whitespace removal)" );
        $white_elements = array();
        reset( $elements );
        while ( ( $key = key( $elements ) ) !== null )
        {
            unset( $element );
            $element =& $elements[$key];
            next( $elements );
            $next_key = key( $elements );
            unset( $next_element );
            $next_element = null;
            if ( $next_key !== null )
                $next_element =& $elements[$next_key];
            switch ( $element["Type"] )
            {
                case EZ_ELEMENT_COMMENT:
                {
                    // Ignore comments
                } break;
                case EZ_ELEMENT_TEXT:
                case EZ_ELEMENT_VARIABLE:
                {
                    if ( $next_element !== null )
                    {
                        switch ( $next_element["Type"] )
                        {
                            case EZ_ELEMENT_END_TAG:
                            case EZ_ELEMENT_SINGLE_TAG:
                            case EZ_ELEMENT_NORMAL_TAG:
                            {
                                unset( $text );
                                $text =& $element["Text"];
                                $text_cnt = strlen( $text );
                                if ( $text_cnt > 0 )
                                {
                                    $char = $text[$text_cnt - 1];
                                    if ( $char == "\n" )
                                    {
                                        $text = substr( $text, 0, $text_cnt - 1 );
                                    }
                                }
                            } break;
                        }
                    }
                    if ( !empty( $element["Text"] ) )
                        $white_elements[] =& $element;
                } break;
                case EZ_ELEMENT_END_TAG:
                case EZ_ELEMENT_SINGLE_TAG:
                case EZ_ELEMENT_NORMAL_TAG:
                {
                    unset( $name );
                    $name =& $element["Name"];
                    if ( isset( $this->Literals[$name] ) )
                    {
                        unset( $text );
                        $text = "";
                        $key = key( $elements );
                        while ( $key !== null )
                        {
                            unset( $element );
                            $element =& $elements[$key];
                            switch ( $element["Type"] )
                            {
                                case EZ_ELEMENT_END_TAG:
                                {
                                    if ( $element["Name"] == $name )
                                    {
                                        next( $elements );
                                        $key = null;
                                        $white_elements[] = array( "Text" => $text,
                                                                   "Type" => EZ_ELEMENT_TEXT );
                                    }
                                    else
                                    {
                                        $text .= $ldel . "/" . $element["Text"] . $rdel;
                                        next( $elements );
                                        $key = key( $elements );
                                    }
                                } break;
                                case EZ_ELEMENT_NORMAL_TAG:
                                {
                                    $text .= $ldel . $element["Text"] . $rdel;
                                    next( $elements );
                                    $key = key( $elements );
                                } break;
                                case EZ_ELEMENT_SINGLE_TAG:
                                {
                                    $text .= $ldel . $element["Text"] . "/" . $rdel;
                                    next( $elements );
                                    $key = key( $elements );
                                } break;
                                case EZ_ELEMENT_COMMENT:
                                {
                                    $text .= "$ldel*" . $element["Text"] . "*$rdel";
                                    next( $elements );
                                    $key = key( $elements );
                                } break;
                                default:
                                {
                                    $text .= $element["Text"];
                                    next( $elements );
                                    $key = key( $elements );
                                } break;
                            }
                        }
                    }
                    else
                    {
                        if ( $next_element !== null )
                        {
                            switch ( $next_element["Type"] )
                            {
                                case EZ_ELEMENT_TEXT:
                                case EZ_ELEMENT_VARIABLE:
                                {
                                    unset( $text );
                                    $text =& $next_element["Text"];
                                    $text_cnt = strlen( $text );
                                    if ( $text_cnt > 0 )
                                    {
                                        $char = $text[0];
                                        if ( $char == "\n" )
                                        {
                                            $text = substr( $text, 1 );
                                        }
                                    }
                                } break;
                            }
                        }
                        $white_elements[] =& $element;
                    }
                } break;
            }
        }
        unset( $elements );
        if ( $this->ShowDetails )
            eZDebug::addTimingPoint( "Parse pass 3 (build tree)" );

        $tagStack = array();

        reset( $white_elements );
        while ( ( $key = key( $white_elements ) ) !== null )
        {
            unset( $element );
            $element =& $white_elements[$key];
            switch ( $element["Type"] )
            {
                case EZ_ELEMENT_TEXT:
                {
                    unset( $node );
                    $node = new eZTemplateTextElement( $element["Text"] );
                    $this->setRelation( $node, $relatedResource, $relatedTemplateName );
                    $currentRoot->appendChild( $node );
                } break;
                case EZ_ELEMENT_VARIABLE:
                {
                    $text =& $element["Text"];
                    $text_len = strlen( $text );
                    $var_data =& $this->parseVariableTag( $text, 0, &$var_end, $text_len, $nspace );

                    $node =& new eZTemplateVariableElement( $var_data );
                    $this->setRelation( $node, $relatedResource, $relatedTemplateName );
                    $currentRoot->appendChild( &$node );
                    if ( $var_end < $text_len )
                    {
                        $this->warning( "", "Junk at variable end: '" . substr( $text, $var_end, $text_len - $var_end ) . "' (" . substr( $text, 0, $var_end ) . ")" );
                    }
                } break;
                case EZ_ELEMENT_SINGLE_TAG:
                case EZ_ELEMENT_NORMAL_TAG:
                case EZ_ELEMENT_END_TAG:
                {
                    unset( $text );
                    unset( $type );
                    $text =& $element["Text"];
                    $text_len = strlen( $text );
                    $type =& $element["Type"];

                    $ident_pos = $this->identifierEndPos( $text, 0, $text_len );
                    $tag = substr( $text, 0, $ident_pos - 0 );
                    $attr_pos = $ident_pos;
                    unset( $args );
                    $args = array();
                    while ( $attr_pos < $text_len )
                    {
                        $attr_pos_start = $this->skipWhiteSpace( $text, $attr_pos, $text_len );
                        if ( $attr_pos_start == $attr_pos and
                             $attr_pos_start < $text_len )
                        {
                            $this->error( "", "Expected whitespace, got: '" . substr( $text, $attr_pos ) . "'" );
                            break;
                        }
                        $attr_pos = $attr_pos_start;
                        $attr_name_pos = $this->identifierEndPos( $text, $attr_pos, $text_len );
                        $attr_name = substr( $text, $attr_pos, $attr_name_pos - $attr_pos );
                        if ( $attr_name_pos >= $text_len )
                        {
                            continue;
//                             $this->error( "", "Unterminated parameter in function '$tag' ($text)" );
//                             break;
                        }
                        if ( $text[$attr_name_pos] != "=" )
                        {
                            $this->error( "", "Invalid parameter characters in function '$tag': '" .
                                          substr( $text, $attr_name_pos )  . "'" );
                            break;
                        }
                        ++$attr_name_pos;
                        unset( $var_data );
                        $var_data =& $this->parseVariableTag( $text, $attr_name_pos, &$var_end, $text_len, $nspace );
                        $args[$attr_name] = $var_data;
                        $attr_pos = $var_end;
                    }

                    if ( $type == EZ_ELEMENT_END_TAG and count( $args ) > 0 )
                    {
                        $this->warning( "", "End tag \"$tag\" cannot have attributes" );
                        $args = array();
                    }

                    if ( $type == EZ_ELEMENT_NORMAL_TAG )
                    {
                        unset( $node );
                        $node =& new eZTemplateFunctionElement( $tag, $args );
                        $this->setRelation( $node, $relatedResource, $relatedTemplateName );
                        $currentRoot->appendChild( &$node );
                        $has_children = true;
                        if ( isset( $this->FunctionAttributes[$tag] ) )
                        {
//                             eZDebug::writeDebug( $this->FunctionAttributes[$tag], "\$this->FunctionAttributes[$tag] #1" );
                            if ( is_array( $this->FunctionAttributes[$tag] ) )
                                $this->loadAndRegisterFunctions( $this->FunctionAttributes[$tag] );
                            $has_children = $this->FunctionAttributes[$tag];
                        }
                        else if ( isset( $this->Functions[$tag] ) )
                        {
//                             eZDebug::writeDebug( $this->Functions[$tag], "\$this->Functions[$tag] #1" );
                            if ( is_array( $this->Functions[$tag] ) )
                                $this->loadAndRegisterFunctions( $this->Functions[$tag] );
                            $has_children = $this->hasChildren( $this->Functions[$tag], $tag );
                        }
                        if ( $has_children )
                        {
                            $tagStack[] = array( "Root" => &$currentRoot,
                                                 "Tag" => &$tag );
                            unset( $currentRoot );
                            $currentRoot =& $node;
                        }
                    }
                    else if ( $type == EZ_ELEMENT_END_TAG )
                    {
                        $has_children = true;
                        if ( isset( $this->FunctionAttributes[$tag] ) )
                        {
//                             eZDebug::writeDebug( $this->FunctionAttributes[$tag], "\$this->FunctionAttributes[$tag] #2" );
                            if ( is_array( $this->FunctionAttributes[$tag] ) )
                                $this->loadAndRegisterFunctions( $this->FunctionAttributes[$tag] );
                            $has_children = $this->FunctionAttributes[$tag];
                        }
                        else if ( isset( $this->Functions[$tag] ) )
                        {
//                             eZDebug::writeDebug( $this->Functions[$tag], "\$this->Functions[$tag] #2" );
                            if ( is_array( $this->Functions[$tag] ) )
                                $this->loadAndRegisterFunctions( $this->Functions[$tag] );
                            $has_children = $this->hasChildren( $this->Functions[$tag], $tag );
                        }
                        if ( !$has_children )
                        {
                            $this->warning( "", "End tag \"$tag\" for function which does not accept children, ignoring tag" );
                        }
                        else
                        {
                            unset( $oldTag );
                            unset( $oldTagName );
                            $oldTag =& array_pop( &$tagStack );
                            $oldTagName =& $oldTag["Tag"];
                            unset( $currentRoot );
                            $currentRoot =& $oldTag["Root"];

                            if ( $oldTagName != $tag )
                                $this->warning( "", "Unterminated tag \"$oldTagName\" does not match tag \"$tag\" at $blockStart" );
                        }
                    }
                    else // EZ_ELEMENT_SINGLE_TAG
                    {
                        unset( $node );
                        $node =& new eZTemplateFunctionElement( $tag, $args );
                        $this->setRelation( $node, $relatedResource, $relatedTemplateName );
                        $currentRoot->appendChild( &$node );
                    }
                    unset( $tag );

                } break;
            }
            next( $white_elements );
        }
        unset( $white_elements );
        if ( $this->ShowDetails )
            eZDebug::addTimingPoint( "Parse pass 3 done" );
        return;

    }

    /*!
     Returns the end position of the identifier.
     If no identifier was found the end position is returned.
    */
    function identifierEndPos( &$text, $start_pos, $len )
    {
        $pos = $start_pos;
        while ( $pos < $len )
        {
            if ( !preg_match( "/^[a-zA-Z0-9_-]$/", $text[$pos] ) )
                return $pos;
            ++$pos;
        }
        return $pos;
    }

    /*!
     Returns the end position of the quote $quote.
     If no quote was found the end position is returned.
    */
    function quoteEndPos( &$text, $start_pos, $len, $quote )
    {
        $pos = $start_pos;
        while ( $pos < $len )
        {
            if ( $text[$pos] == "\\" )
                ++$pos;
            else if ( $text[$pos] == $quote )
                return $pos;
            ++$pos;
        }
        return $pos;
    }

    /*!
     Returns the end position of the numeric.
     If no numeric was found the end position is returned.
    */
    function numericEndPos( &$text, $start_pos, $len,
                            $allow_float = true )
    {
//         eZDebug::writeDebug( substr( $text, $start_pos ) );
//         eZDebug::writeDebug( $allow_float ? 'true' : 'false' );
        $pos = $start_pos;
        $has_comma = false;
        if ( $pos < $len )
        {
            if ( $text[$pos] == '-' )
                ++$pos;
        }
        while ( $pos < $len )
        {
//             eZDebug::writeDebug( substr( $text, $pos ) );
            if ( $text[$pos] == "." and $allow_float )
            {
                if ( $has_comma )
                    return $pos;
                $has_comma = true;
            }
            else if ( !preg_match( "/^[0-9]$/", $text[$pos] ) )
                return $pos;
            ++$pos;
        }
        if ( $has_comma and
             $start_pos + 1 == $pos )
            return $start_pos;
        return $pos;
    }

    /*!
     Returns the position of the first non-whitespace characters.
    */
    function skipWhiteSpace( $text, $pos, $text_len )
    {
        if ( $pos >= $text_len )
            return $pos;
        while( $pos < $text_len and
               preg_match( "/[ \t\r\n]/", $text[$pos] ) )
        {
            ++$pos;
        }
        return $pos;
    }

    /*!
     Parses operator syntax.
    */
    function &parseOperators( &$text, $start_pos, &$end, $len, $def_nspace, $start_with_oper = false )
    {
//         eZDebug::writeDebug( "text='$text', start_pos=$start_pos, len=$len, start_with_oper=$start_with_oper", "parseOperators" );
        $pos = $start_pos;
        $operators = array();
        $i = 0;
        while ( $pos < $len and
                ( ( $i == 0 and $start_with_oper ) or $text[$pos] == "|" ) )
        {
            if ( $i != 0 or !$start_with_oper )
                ++$pos;
            $end_pos = $this->identifierEndPos( $text, $pos, $len );
            $operator_name = substr( $text, $pos, $end_pos - $pos );
            $pos = $end_pos;
            $params = array();
            if ( $pos < $len and
                 $text[$pos] == "(" )
            {
                ++$pos;
                while ( $pos < $len and
                        $text[$pos] != ")" )
                {
                    if ( $text[$pos] == "," )
                    {
                        $param = $this->emptyVariable();
                        ++$pos;
                    }
                    else
                    {
                        $param = $this->parseVariableTag( $text, $pos, $end_pos, $len, $def_nspace );
                        $pos = $end_pos;
                        if ( $text[$pos] == "," )
                            ++$pos;
                        else if ( $text[$pos] != ")" )
                            eZDebug::writeWarning( "Parameter didn't end with a ) or , \{pos=$pos,text='" .
                                                   $text[$pos] . "',before=\"" .
                                                   substr( $text, max( $pos - 5, 0 ), 5 ) . "\",after=\"" .
                                                   substr( $text, min( $pos + 1, $len - 1 ), 5 ) . "\"}", "eZTemplate" );
                    }
                    $params[] = $param;
                }
                ++$pos;
            }
            $operator = new eZTemplateOperatorElement( $operator_name, $params );
            $this->setRelation( $operator, $this->CurrentRelatedResource, $this->CurrentRelatedTemplateName );
            $operators[] = $operator;
            ++$i;
        }
        $end = $pos;
//         eZDebug::writeDebug( "text='$text', end=$end, len=$len", "parseOperators:end" );
        return $operators;
    }

    /*!
     Parses attribute syntax.
    */
    function &parseAttribute( &$text, $start_pos, &$end, $len, $def_nspace )
    {
//         eZDebug::writeDebug( "text='$text', start_pos=$start_pos, len=$len", "parseAttribute" );
        $pos = $start_pos;
        $struct = array();
        if ( $text[$pos] == "$" )
        {
            $var = $this->parseVariableTag( $text, $pos, $end_pos, $len, $def_nspace );
            $struct["type"] = "variable";
            $struct["content"] = $var;
        }
        else if ( preg_match( "/^[0-9]$/", $text[$pos] ) )
        {
            $end_pos = $this->numericEndPos( $text, $pos, $len );
            $struct["type"] = "index";
            $struct["content"] = substr( $text, $pos, $end_pos - $pos );
        }
        else
        {
            $end_pos = $this->identifierEndPos( $text, $pos, $len );
            $struct["type"] = "map";
            $struct["content"] = substr( $text, $pos, $end_pos - $pos );
        }
        $end = $end_pos;
        return $struct;
    }

    /*!
     Parses variables and attributes.
    */
    function &parseVariableAndAttributes( &$text, $start_pos, &$end, $len, $def_nspace )
    {
//         eZDebug::writeDebug( "text='$text', start_pos=$start_pos, len=$len", "parseVariableAndAttributes" );
        $pos = $start_pos;
        $nspace = false;
        $var = "";
        $end_pos = $pos;
        do
        {
            if ( $text[$end_pos] == ":" )
            {
                if ( $nspace !== false )
                    $nspace = "$nspace:$var";
                else
                    $nspace = $var;
                ++$pos;
            }
            $end_pos = $this->identifierEndPos( $text, $pos, $len );
            $var = substr( $text, $pos, $end_pos - $pos );
            $pos = $end_pos;
        } while( $end_pos < $len and $text[$end_pos] == ":" );
        if ( $def_nspace != "" )
        {
            if ( $nspace != "" )
                $nspace = "$def_nspace:$nspace";
            else
                $nspace = $def_nspace;
        }
        $struct["namespace"] = $nspace;
        $struct["type"] = "variable";
        $struct["name"] = $var;
        $attributes = array();
        $pos = $end_pos;
        while ( $pos < $len and
                ( $text[$pos] == "." or $text[$pos] == "[" ) )
        {
            if ( $text[$pos] == "." )
            {
                ++$pos;
                $attribute = $this->parseAttribute( $text, $pos, $attr_end, $len, $def_nspace );
                $pos = $attr_end;
            }
            else if ( $text[$pos] == "[" )
            {
                ++$pos;
                $attribute = $this->parseAttribute( $text, $pos, $attr_end, $len, $def_nspace );
                $pos = $attr_end;
                if ( $text[$attr_end] == "]" )
                {
                    ++$pos;
                }
                else
                    eZDebug::writeWarning( "Indexing didn't end with a ]", "eZTemplate::parseVariableAndAttributes" );
            }
            $attributes[] = $attribute;
        }
        $struct["attributes"] = $attributes;
        $end = $pos;
        return $struct;
    }

    /*!
     Returns the empty variable type.
    */
    function emptyVariable()
    {
        return array( "type" => "null" );
    }

    /*!
     Parses the variable and operators into a structure.
    */
    function &parseVariableTag( &$text, $start_pos, &$end, $len, $def_nspace )
    {
//         eZDebug::writeDebug( "text='$text', start_pos=$start_pos, len=$len", "parseVariableTag" );
        $pos = $start_pos;
        $struct = array();
        $end_pos = $pos;
//         while( $pos < $len )
//         {
            if ( $text[$pos] == "$" )
            {
                ++$pos;
//                 eZDebug::writeDebug( "parseVariableAndAttributes", "parseVariableTag:choice" );
                $struct = $this->parseVariableAndAttributes( $text, $pos, $var_end, $len, $def_nspace );
                $end_pos = $var_end;
            }
            else if ( $text[$pos] == "'" or
                      $text[$pos] == '"' )
            {
                $quote = $text[$pos];
                ++$pos;
//                 eZDebug::writeDebug( "quoteEndPos", "parseVariableTag:choice" );
                $end_pos = $this->quoteEndPos( $text, $pos, $len, $quote );
                $struct["type"] = "text";
                $struct["content"] = substr( $text, $pos, $end_pos - $pos );
                ++$end_pos;
            }
            else
            {
                $end_pos = $this->numericEndPos( $text, $pos, $len );
                if ( $end_pos == $pos )
                {
                    $end_pos = $this->identifierEndPos( $text, $pos, $len );
                    if ( $end_pos < $len and
                         $text[$end_pos] == "(" )
                    {
//                         eZDebug::writeDebug( "parseOperators", "parseVariableTag:choice" );
                        $operators = $this->parseOperators( $text, $pos, $end_pos, $len, $def_nspace, true );
                        $struct["type"] = "operators";
                        $struct["operators"] = $operators;
                    }
                    else
                    {
                        $struct["type"] = "text";
                        $struct["content"] = substr( $text, $pos, $end_pos - $pos );
                    }
                }
                else
                {
                    $struct["type"] = "numerical";
                    $struct["content"] = substr( $text, $pos, $end_pos - $pos );
                }
            }
//             if ( $end_pos > $start_pos and
//                  ( $text[$end_pos] == "." or
//                    $text[$end_pos] == "[" ) )
//             {
//                 if ( $text[$end_pos] == "." )
//                 {
//                     ++$end_pos;
//                     $attribute = $this->parseAttribute( $text, $end_pos, $attr_end, $len, $def_nspace );
//                     if ( count( $struct ) == 0 )
//                         $struct = $attribute;
//                     else
//                     {
//                         if ( $struct["type"] == "container" )
//                         {
//                         }
//                         else
//                         {
//                             $old_struct = $struct;
//                             $struct = array();
//                             $struct["type"] = "container";
//                             $struct["content"] = $old_struct;
//                             $struct["attributes"] = $attribute;
//                         }
//                     }
//                     $end_pos = $attr_end;
//                 }
//                 else if ( $text[$end_pos] == "[" )
//                 {
//                     ++$end_pos;
//                     $attribute = $this->parseAttribute( $text, $end_pos, $attr_end, $len, $def_nspace );
//                     $end_pos = $attr_end;
//                     if ( $text[$attr_end] == "]" )
//                     {
//                         ++$end_pos;
//                     }
//                     else
//                         echo( "Indexing didn't end with a ]\n" );
//                 }
//                 $attributes[] = $attribute;
//             }
            if ( $end_pos < $len and
                 $text[$end_pos] == "|" )
            {
                $pos = $end_pos;
//                 eZDebug::writeDebug( "parseOperators#2", "parseVariableTag:choice" );
                $struct["operators"] = $this->parseOperators( $text, $pos, $end_pos, $len, $def_nspace );
            }
            if ( $pos == $end_pos )
            {
                break;
            }
            $pos = $end_pos;
//         }
        $end = $end_pos;
        return $struct;
    }

    /*!
     Returns the actual value of a template type or null if an unknown type.
    */
    function &elementValue( &$data, $nspace )
    {
        $value = null;
        if ( !is_array( $data ) or
             !isset( $data['type'] ) )
        {
            $this->error( "elementValue",
                          "Missing data structure" );
            return null;
        }
        switch ( $data["type"] )
        {
            case "text":
            {
                $value =& $data["content"];
            } break;
            case "numerical":
            {
                $value =& $data["content"];
            } break;
            case "null":
            {
                $value = null;
            } break;
            case "operators":
            {
                $value = null;
            } break;
            case "variable":
            {
                $value =& $this->variableElementValue( $data, $nspace );
            } break;
            default:
            {
                $this->error( "elementValue",
                              "Unknown variable type: '" . $data["type"] . "'" );
                return null;
            }
        }
        $ops =& $data["operators"];
        if ( count( $ops ) > 0 )
        {
            reset( $ops );
            while ( ( $key = key( $ops ) ) !== null )
            {
                $op =& $ops[$key];
                unset( $tmp_value );
                $tmp_value = $value;
                $op->process( $this, $tmp_value, $nspace, $nspace );
                $value =& $tmp_value;
                next( $ops );
            }
        }
        return $value;
    }

    /*!
     Returns the value of the template variable $data, or null if no defined variable for that name.
    */
    function &variableElementValue( &$data, $def_nspace )
    {
        if ( $data["type"] != "variable" )
            return null;
        $nspace = $data["namespace"];
        if ( $nspace === false )
            $nspace = $def_nspace;
        else
        {
            if ( $def_nspace != "" )
                $nspace = $def_nspace . ':' . $nspace;
        }
        $name = $data["name"];
        if ( !$this->hasVariable( $name, $nspace ) )
        {
            $var_name = $name;
            $this->warning( "", "Undefined variable: \"$var_name\"" . ( $nspace != "" ? " in namespace \"$nspace\"" : "" ) );
            return null;
        }
        $value =& $this->variable( $name, $nspace );
        $return_value =& $value;
        $attrs =& $data["attributes"];
        if ( count( $attrs ) > 0 )
        {
            reset( $attrs );
            while( ( $key = key( $attrs ) ) !== null )
            {
                $attr =& $attrs[$key];
                $attr_value = $this->attributeValue( $attr, $def_nspace );
                if ( !is_null( $attr_value ) )
                {
                    if ( !is_numeric( $attr_value ) and
                         !is_string( $attr_value ) )
                    {
                        $this->error( "",
                                      "Cannot use type " . gettype( $attr_value ) . " for attribute lookup" );
                        return null;
                    }
                    if ( is_array( $return_value ) )
                    {
                        if ( isset( $return_value[$attr_value] ) )
                            $return_value =& $return_value[$attr_value];
                        else
                        {
                            $this->error( "",
                                          "No such attribute for array: $attr_value" );
                            return null;
                        }
                    }
                    else if ( is_object( $return_value ) )
                    {
                        if ( method_exists( $return_value, "attribute" ) and
                             method_exists( $return_value, "hasattribute" ) )
                        {
                            if ( $return_value->hasAttribute( $attr_value ) )
                            {
                                unset( $return_attribute_value );
                                $return_attribute_value =& $return_value->attribute( $attr_value );
                                unset( $return_value );
                                $return_value =& $return_attribute_value;
                            }
                            else
                            {
                                $this->error( "",
                                              "No such attribute for object: $attr_value" );
                                return null;
                            }
                        }
                        else
                        {
                            $this->error( "",
                                          "Cannot retrieve attribute of object(" . get_class( $return_value ) .
                                          "), no attribute functions available." );
                            return null;
                        }
                    }
                    else
                    {
                        $this->error( "",
                                      "Cannot retrieve attribute of a " . gettype( $return_value ) );
                        return null;
                    }
                }
                else
                    return null;
                next( $attrs );
            }
        }
        return $return_value;
    }

    /*!
     Return the identifier used for attribute lookup.
    */
    function attributeValue( &$data, $nspace )
    {
        switch ( $data["type"] )
        {
            case "map":
            {
                return $data["content"];
            } break;
            case "index":
            {
                return $data["content"];
            } break;
            case "variable":
            {
                return $this->elementValue( $data["content"], $nspace );
            } break;
            default:
            {
                $this->error( "attributeValue()", "Unknown attribute type: " . $data["type"] );
                return null;
            }
        }
    }

    /*!
     Helper function for creating a displayable text for a variable.
    */
    function &variableText( &$var, $namespace = "", $attrs = array() )
    {
        $txt = "$";
        if ( $namespace != "" )
            $txt .= "$namespace:";
        $txt .= $var;
        if ( count( $attrs ) > 0 )
            $txt .= "." . implode( ".", $attrs );
        return $txt;
    }

    /*!
     Returns the named parameter list for the operator $name.
    */
    function operatorParameterList( $name )
    {
        $param_list = array();
        if ( is_array( $this->Operators[$name] ) )
        {
//             eZDebug::writeDebug( $this->Operators[$name], "\$this->Operators[$name]" );
            $this->loadAndRegisterOperators( $this->Operators[$name] );
        }
        $op =& $this->Operators[$name];
        if ( isset( $op ) and
             method_exists( $op, "namedparameterlist" ) )
        {
            $param_list = $op->namedParameterList();
            if ( method_exists( $op, "namedparameterperoperator" ) and
                 $op->namedParameterPerOperator() )
            {
                if ( !isset( $param_list[$name] ) )
                    return array();
                $param_list = $param_list[$name];
            }
        }
        return $param_list;
    }

    /*!
     Tries to run the operator $operatorName with parameters $operatorParameters
     on the value $value.
    */
    function doOperator( &$element, &$namespace, &$current_nspace, &$value, &$operatorName, &$operatorParameters, &$named_params )
    {
        if ( is_array( $this->Operators[$operatorName] ) )
        {
//             eZDebug::writeDebug( $this->Operators[$operatorName], "\$this->Operators[$operatorName]" );
            $this->loadAndRegisterOperators( $this->Operators[$operatorName] );
        }
        $op =& $this->Operators[$operatorName];
        if ( isset( $op ) )
        {
            $op->modify( $element, $this, $operatorName, $operatorParameters, $namespace, $current_nspace, $value, $named_params );
        }
        else
            $this->warning( "", "Operator \"$operatorName\" is not registered" );
    }

    /*!
     Tries to run the function object $func_obj
    */
    function &doFunction( &$name, &$func_obj, $nspace, $current_nspace )
    {
        $func =& $this->Functions[$name];
        if ( isset( $func ) )
        {
            return $func->process( $this, $name, $func_obj, $nspace, $current_nspace );
        }
        else
        {
            $this->warning( "", "Function \"$name\" is not registered" );
            $str = false;
            return $str;
        }
    }

    /*!
     Fetches the result of the template file and displays it.
     If $template is supplied it will load this template file first.
    */
    function display( $template = false, $extraParameters = false )
    {
        $output =& $this->fetch( $template, $extraParameters );
        if ( $this->ShowDetails )
        {
            echo '<h1>Result:</h1>' . "\n";
            echo '<hr/>' . "\n";
        }
        echo "$output";
        if ( $this->ShowDetails )
        {
            echo '<hr/>' . "\n";
        }
        if ( $this->ShowDetails )
        {
            echo "<h1>Template data:</h1>";
            echo "<p class=\"filename\">" . $template . "</p>";
            echo "<pre class=\"example\">" . htmlspecialchars( $this->Text ) . "</pre>";
            reset( $this->IncludeText );
            while ( ( $key = key( $this->IncludeText ) ) !== null )
            {
                $item =& $this->IncludeText[$key];
                echo "<p class=\"filename\">" . $key . "</p>";
                echo "<pre class=\"example\">" . htmlspecialchars( $item ) . "</pre>";
                next( $this->IncludeText );
            }
            echo "<h1>Result text:</h1>";
            echo "<p class=\"filename\">" . $template . "</p>";
            echo "<pre class=\"example\">" . htmlspecialchars( $output ) . "</pre>";
            reset( $this->IncludeOutput );
            while ( ( $key = key( $this->IncludeOutput ) ) !== null )
            {
                $item =& $this->IncludeOutput[$key];
                echo "<p class=\"filename\">" . $key . "</p>";
                echo "<pre class=\"example\">" . htmlspecialchars( $item ) . "</pre>";
                next( $this->IncludeOutput );
            }
        }
    }

    /*!
     Tries to fetch the result of the template file and returns it.
     If $template is supplied it will load this template file first.
    */
    function &fetch( $template = false, $extraParameters = false )
    {
        if ( is_string( $template ) )
            $this->load( $template, $extraParameters );
        $text = "";
        if ( $this->ShowDetails )
            eZDebug::addTimingPoint( "Process" );
        $this->Tree->process( $this, $text, "", "" );
        if ( $this->ShowDetails )
            eZDebug::addTimingPoint( "Process done" );
        return $text;
    }

    /*!
     Sets the template variable $var to the value $val.
     \sa setVariableRef
    */
    function setVariable( $var, $val, $namespace = "" )
    {
        if ( isset( $this->Variables[$namespace][$var] ) )
            unset( $this->Variables[$namespace][$var] );
        $this->Variables[$namespace][$var] = $val;
    }

    /*!
     Sets the template variable $var to the value $val.
     \note This sets the variable using reference
     \sa setVariable
    */
    function setVariableRef( $var, &$val, $namespace = "" )
    {
        if ( isset( $this->Variables[$namespace][$var] ) )
            unset( $this->Variables[$namespace][$var] );
        $this->Variables[$namespace][$var] =& $val;
    }

    /*!
     Removes the template variable $var. If the variable does not exists an error is output.
    */
    function unsetVariable( $var, $namespace = "" )
    {
        if ( isset( $this->Variables[$namespace][$var] ) )
             unset( $this->Variables[$namespace][$var] );
        else
            $this->warning( "unsetVariable()", "Undefined Variable: \$$namespace:$var, cannot unset" );
    }

    /*!
     Returns true if the variable $var is set in namespace $namespace,
     if $attrs is supplied alle attributes must exist for the function to return true.
    */
    function hasVariable( $var, $namespace = "", $attrs = array() )
    {
        $exists = isset( $this->Variables[$namespace][$var] );
        if ( $exists and count( $attrs ) > 0 )
        {
            $ptr =& $this->Variables[$namespace][$var];
            foreach( $attrs as $attr )
            {
                unset( $tmp );
                if ( is_object( $ptr ) )
                {
                    if ( $ptr->hasAttribute( $attr ) )
                        $tmp =& $ptr->attribute( $attr );
                    else
                        return false;
                }
                else if ( is_array( $ptr ) )
                {
                    if ( isset( $ptr[$attr] ) )
                        $tmp =& $ptr[$attr];
                    else
                        return false;
                }
                else
                {
                    return false;
                }
                unset( $ptr );
                $ptr =& $tmp;
            }
        }
        return $exists;
    }

    /*!
     Returns the content of the variable $var using namespace $namespace,
     if $attrs is supplied the result of the attributes is returned.
    */
    function &variable( $var, $namespace = "", $attrs = array() )
    {
        $val = null;
        $exists = isset( $this->Variables[$namespace][$var] );
        if ( $exists )
        {
            if ( count( $attrs ) > 0 )
            {
                $ptr =& $this->Variables[$namespace][$var];
                foreach( $attrs as $attr )
                {
                    unset( $tmp );
                    if ( is_object( $ptr ) )
                    {
                        if ( $ptr->hasAttribute( $attr ) )
                            $tmp =& $ptr->attribute( $attr );
                        else
                            return $val;
                    }
                    else if ( is_array( $ptr ) )
                    {
                        if ( isset( $ptr[$attr] ) )
                            $tmp =& $ptr[$attr];
                        else
                            return $val;
                    }
                    else
                        return $val;
                    unset( $ptr );
                    $ptr =& $tmp;
                }
                if ( isset( $ptr ) )
                    return $ptr;
            }
            else
            {
                $val =& $this->Variables[$namespace][$var];
            }
        }
        return $val;
    }

    /*!
     Returns the attribute(s) of the template variable $var,
     $attrs is an array of attribute names to use iteratively for each new variable returned.
    */
    function &variableAttribute( &$var, $attrs )
    {
        if ( count( $attrs ) > 0 )
        {
            $ptr =& $var;
            foreach( $attrs as $attr )
            {
                unset( $tmp );
                if ( is_object( $ptr ) )
                {
                    if ( $ptr->hasAttribute( $attr ) )
                        $tmp =& $ptr->attribute( $attr );
                    else
                        return $val;
                }
                else if ( is_array( $ptr ) )
                {
                    if ( isset( $ptr[$attr] ) )
                        $tmp =& $ptr[$attr];
                    else
                        return $val;
                }
                else
                    return $val;
                unset( $ptr );
                $ptr =& $tmp;
            }
            if ( isset( $ptr ) )
                return $ptr;
        }
        return null;
    }

    /*!
    */
    function appendElement( &$text, &$item, $nspace, $name )
    {
        if ( is_object( $item ) )
        {
            $hasTemplateData = false;
            if ( method_exists( $item, 'templateData' ) )
            {
                $templateData =& $item->templateData();
                if ( is_array( $templateData ) and
                     isset( $templateData['type'] ) )
                {
                    $templateType =& $templateData['type'];
                    if ( $templateType == 'template' and
                         isset( $templateData['uri'] ) and
                         isset( $templateData['template_variable_name'] ) )
                    {
                        $templateURI =& $templateData['uri'];
                        $templateVariableName =& $templateData['template_variable_name'];
                        $templateText = '';
                        include_once( 'lib/eztemplate/classes/eztemplateincludefunction.php' );
                        $this->setVariableRef( $templateVariableName, $item, $name );
                        eZTemplateIncludeFunction::handleInclude( $templateText, $templateURI, $this, $nspace, $name );
                        $this->appendElement( $text, $templateText, $nspace, $name );
                        $hasTemplateData = true;
                    }
                }
            }
            if ( !$hasTemplateData )
                $text .= 'Object(' . get_class( $item ) . ')';
        }
        else
            $text .= $item;
    }

    /*!
     Registers the functions supplied by the object $functionObject.
     The object must have a function called functionList()
     which returns an array of functions this object handles.
     If the object has a function called attributeList()
     it is used for registering function attributes.
     The function returns an associative array with each key being
     the name of the function and the value being a boolean.
     If the boolean is true the function will have children.
    */
    function registerFunctions( &$functionObject )
    {
        $this->registerFunctionsInternal( $functionObject );
    }

    /*!
    */
    function registerAutoloadFunctions( $functionDefinition )
    {
        if ( ( ( isset( $functionDefinition['function'] ) or
                 ( isset( $functionDefinition['script'] ) and
                   isset( $functionDefinition['class'] ) ) ) and
               isset( $functionDefinition['function_names'] ) ) )
        {
            foreach ( $functionDefinition['function_names'] as $functionName )
            {
//                 eZDebug::writeDebug( "Autoload for function $functionName", 'eztemplate:registerAutoloadFunctions' );
                $this->Functions[$functionName] =& $functionDefinition;
            }
            if ( isset( $functionDefinition['function_attributes'] ) )
            {
                foreach ( $functionDefinition['function_attributes'] as $functionAttributeName )
                {
//                     eZDebug::writeDebug( "Autoload for function attribute $functionAttributeName", 'eztemplate:registerAutoloadFunctions' );
                    unset( $this->FunctionAttributes[$functionAttributeName] );
                    $this->FunctionAttributes[$functionAttributeName] =& $functionDefinition;
                }
            }
        }
        else
            $this->error( 'registerFunctions', 'Cannot register function definition, missing data' );
    }

    function loadAndRegisterFunctions( $functionDefinition )
    {
//         if ( is_object( $this->Functions[$functionName] ) )
//             return true;
//         $functionDefinition =& $this->Functions[$functionName];
        $functionObject = null;
        if ( isset( $functionDefinition['function'] ) )
        {
            $function = $functionDefinition['function'];
//             eZDebug::writeDebug( "registering with function=$function", 'eztemplate:loadAndRegisterFunctions' );
            if ( function_exists( $function ) )
                $functionObject =& $function();
        }
        else if ( isset( $functionDefinition['script'] ) )
        {
            $script = $functionDefinition['script'];
            $class = $functionDefinition['class'];
//             eZDebug::writeDebug( "registering with script=$script and class=$class", 'eztemplate:loadAndRegisterFunctions' );
            include_once( $script );
            if ( class_exists( $class ) )
                $functionObject = new $class();
        }
        if ( is_object( $functionObject ) )
        {
            $this->registerFunctionsInternal( $functionObject, true );
//             eZDebug::writeDebug( "registering was succesful", 'eztemplate:loadAndRegisterFunctions' );
            return true;
        }
//         eZDebug::writeDebug( "registering failed", 'eztemplate:loadAndRegisterFunctions' );
        return false;
    }

    /*!
     \private
    */
    function registerFunctionsInternal( &$functionObject, $debug = false )
    {
        if ( !is_object( $functionObject ) or
             !method_exists( $functionObject, 'functionList' ) )
            return false;
        foreach ( $functionObject->functionList() as $functionName )
        {
//             if ( $debug )
//                 eZDebug::writeDebug( "Registering function $functionName", 'eztemplate:registerFunctionsInternal' );
            $this->Functions[$functionName] =& $functionObject;
        }
        if ( method_exists( $functionObject, "attributeList" ) )
        {
            $functionAttributes = $functionObject->attributeList();
            foreach ( $functionAttributes as $attributeName => $hasChildren )
            {
                unset( $this->FunctionAttributes[$attributeName] );
                $this->FunctionAttributes[$attributeName] = $hasChildren;
//                 if ( $debug )
//                 {
//                     eZDebug::writeDebug( "Registering function attribute $attributeName, hasChildren=$hasChildren", 'eztemplate:registerFunctionsInternal' );
//                     eZDebug::writeDebug( $this->FunctionAttributes[$attributeName], "\$this->FunctionAttributes[$attributeName] #3" );
//                 }
            }
        }
        return true;
    }

    /*!
     Registers the function $func_name to be bound to object $func_obj.
     If the object has a function called attributeList()
     it is used for registering function attributes.
     The function returns an associative array with each key being
     the name of the function and the value being a boolean.
     If the boolean is true the function will have children.
    */
    function registerFunction( $func_name, &$func_obj )
    {
        $this->Functions[$func_name] =& $func_obj;
        if ( method_exists( $func_obj, "attributeList" ) )
        {
            $attrs = $func_obj->attributeList();
            while ( list( $attr_name, $has_children ) = each( $attrs ) )
            {
                $this->FunctionAttributes[$attr_name] = $has_children;
            }
        }
    }

    /*!
     Registers a new literal tag in which the tag will be transformed into
     a text element.
    */
    function registerLiteral( $func_name )
    {
        $this->Literals[$func_name] = true;
    }

    /*!
     Removes the literal tag $func_name.
    */
    function unregisterLiteral( $func_name )
    {
        unset( $this->Literals[$func_name] );
    }

    /*!
    */
    function registerAutoloadOperators( $operatorDefinition )
    {
        if ( ( ( isset( $operatorDefinition['function'] ) or
                 ( isset( $operatorDefinition['script'] ) and
                   isset( $operatorDefinition['class'] ) ) ) and
               isset( $operatorDefinition['operator_names'] ) ) )
        {
            foreach ( $operatorDefinition['operator_names'] as $operatorName )
            {
//                 eZDebug::writeDebug( "Autoload for operator $operatorName", 'eztemplate:registerAutoloadOperators' );
                $this->Operators[$operatorName] =& $operatorDefinition;
            }
        }
        else
            $this->error( 'registerOperators', 'Cannot register operator definition, missing data' );
    }

    function loadAndRegisterOperators( $operatorDefinition )
    {
        $operatorObject = null;
        if ( isset( $operatorDefinition['function'] ) )
        {
            $function = $operatorDefinition['function'];
//             eZDebug::writeDebug( "registering with function=$function", 'eztemplate:loadAndRegisterOperators' );
            if ( function_exists( $function ) )
                $operatorObject =& $function();
        }
        else if ( isset( $operatorDefinition['script'] ) )
        {
            $script = $operatorDefinition['script'];
            $class = $operatorDefinition['class'];
//             eZDebug::writeDebug( "registering with script=$script and class=$class", 'eztemplate:loadAndRegisterOperators' );
            include_once( $script );
            if ( class_exists( $class ) )
            {
                if ( isset( $operatorDefinition['class_parameter'] ) )
                    $operatorObject = new $class( $operatorDefinition['class_parameter'] );
                else
                    $operatorObject = new $class();
            }
        }
        if ( is_object( $operatorObject ) )
        {
            $this->registerOperatorsInternal( $operatorObject, true );
//             eZDebug::writeDebug( "registering was succesful", 'eztemplate:loadAndRegisterOperators' );
            return true;
        }
//         eZDebug::writeDebug( "registering failed", 'eztemplate:loadAndRegisterOperators' );
        return false;
    }

    /*!
     Registers the operators supplied by the object $operatorObject.
     The function operatorList() must return an array of operator names.
    */
    function registerOperators( &$operatorObject )
    {
        $this->registerOperatorsInternal( $operatorObject );
    }

    /*!
    */
    function registerOperatorsInternal( &$operatorObject, $debug = false )
    {
        if ( !is_object( $operatorObject ) or
             !method_exists( $operatorObject, 'operatorList' ) )
            return false;
        foreach( $operatorObject->operatorList() as $operatorName )
        {
//             if ( $debug )
//                 eZDebug::writeDebug( "Registering operator $operatorName", 'eztemplate:registerOperatorsInternal' );
            $this->Operators[$operatorName] =& $operatorObject;
        }
    }

    /*!
     Registers the operator $op_name to use the object $op_obj.
    */
    function registerOperator( $op_name, &$op_obj )
    {
        $this->Operators[$op_name] =& $op_obj;
    }

    /*!
     Unregisters the operator $op_name.
    */
    function unregisterOperator( $op_name )
    {
        if ( is_array( $op_name ) )
        {
            foreach ( $op_name as $op )
            {
                $this->unregisterOperator( $op_name );
            }
        }
        else if ( isset( $this->Operators ) )
            unset( $this->Operators[$op_name] );
        else
            $this->warning( "unregisterOpearator()", "Operator $op_name is not registered, cannot unregister" );
    }

    /*!
     Not implemented yet.
    */
    function registerFilter()
    {
    }

    /*!
     Registers a new resource object $res.
     The resource object take care of fetching templates using an URI.
    */
    function registerResource( &$res )
    {
        if ( is_object( $res ) )
            $this->Resources[$res->resourceName()] =& $res;
        else
            $this->warning( "registerResource()", "Supplied argument is not a resource object" );
    }

    /*!
     Unregisters the resource $res_name.
    */
    function unregisterResource( $res_name )
    {
        if ( is_array( $res_name ) )
        {
            foreach ( $res_name as $res )
            {
                $this->unregisterResource( $res );
            }
        }
        else if ( isset( $this->Resources[$res_name] ) )
            unset( $this->Resources[$res_name] );
        else
            $this->warning( "unregisterResource()", "Resource $res_name is not registered, cannot unregister" );
    }

    /*!
     Sets whether detail output is used or not.
     Detail output is useful for debug output where you want to examine the template
     and the output text.
    */
    function setShowDetails( $show )
    {
        $this->ShowDetails = $show;
    }

    /*!
     Outputs a warning about the parameter $param missing for function/operator $name.
    */
    function missingParameter( $name, $param )
    {
        $this->warning( $name, "Missing parameter $param" );
    }

    /*!
     Outputs a warning about the variable $var being undefined.
    */
    function undefinedVariable( $name, $var )
    {
        $this->warning( $name, "Undefined variable: $var" );
    }

    /*!
     Outputs an error about the template function $func_name being undefined.
    */
    function undefinedFunction( $func_name )
    {
        $this->error( "", "Undefined function: $func_name" );
    }

    /*!
     Displays a warning for the function/operator $name and text $txt.
    */
    function warning( $name, $txt )
    {
        if ( $name != "" )
            eZDebug::writeWarning( $txt, "eZTemplate:$name" );
        else
            eZDebug::writeWarning( $txt, "eZTemplate" );
    }

    /*!
     Displays an error for the function/operator $name and text $txt.
    */
    function error( $name, $txt )
    {
        if ( $name != "" )
            eZDebug::writeError( $txt, "eZTemplate:$name" );
        else
            eZDebug::writeError( $txt, "eZTemplate" );
    }

    /*!
     Sets the cached template tree for \a $uri to \a $root.
    */
    function setCachedTemplateTree( $uri, &$root )
    {
        $this->TemplateTrees[$uri] =& $root;
    }

    /*!
     Sets the original text for uri $uri to $text.
    */
    function setIncludeText( $uri, &$text )
    {
        $this->IncludeText[$uri] =& $text;
    }

    /*!
     Sets the output for uri $uri to $output.
    */
    function setIncludeOutput( $uri, &$output )
    {
        $this->IncludeOutput[$uri] =& $output;
    }

    /*!
     \return the path list which is used for autoloading functions and operators.
    */
    function autoloadPathList()
    {
        return $this->AutoloadPathList;
    }

    /*!
     Sets the path list for autoloading.
    */
    function setAutoloadPathList( $pathList )
    {
        $this->AutoloadPathList = $pathList;
    }

    /*!
     Looks trough the pathes specified in autoloadPathList() and fetches autoload
     definition files used for autoloading functions and operators.
    */
    function autoload()
    {
        $pathList =& $this->autoloadPathList();
        foreach ( $pathList as $path )
        {
            $autoloadFile = $path . '/eztemplateautoload.php';
            if ( file_exists( $autoloadFile ) )
            {
                unset( $eZTemplateOperatorArray );
                unset( $eZTemplateFunctionArray );
                include( $autoloadFile );
                if ( isset( $eZTemplateOperatorArray ) and
                     is_array( $eZTemplateOperatorArray ) )
                {
                    foreach ( $eZTemplateOperatorArray as $operatorDefinition )
                    {
                        $this->registerAutoloadOperators( $operatorDefinition );
                    }
                }
                if ( isset( $eZTemplateFunctionArray ) and
                     is_array( $eZTemplateFunctionArray ) )
                {
                    foreach ( $eZTemplateFunctionArray as $functionDefinition )
                    {
                        $this->registerAutoloadFunctions( $functionDefinition );
                    }
                }
            }
        }
    }

    /*!
     Returns the globale template instance, creating it if it does not exist.
    */
    function &instance()
    {
        $tpl =& $GLOBALS["eZTemplateInstance"];
        if ( get_class( $tpl ) != "eztemplate" )
        {
            $tpl = new eZTemplate();
        }
        return $tpl;
    }

    /*!
     Returns the INI object for the template.ini file.
    */
    function &ini()
    {
        include_once( "lib/ezutils/classes/ezini.php" );
        $ini =& eZINI::instance( "template.ini" );
        return $ini;
    }

    /*!
     \static
     \return true if debugging of internals is enabled, this will display
     which files are loaded and when cache files are created.
      Set the option with setIsDebugEnabled().
    */
    function isDebugEnabled()
    {
        if ( !isset( $GLOBALS['eZTemplateDebugInternalsEnabled'] ) )
             $GLOBALS['eZTemplateDebugInternalsEnabled'] = EZ_TEMPLATE_DEBUG_INTERNALS;
        return $GLOBALS['eZTemplateDebugInternalsEnabled'];
    }

    /*!
     \static
     Sets whether internal debugging is enabled or not.
    */
    function setIsDebugEnabled( $debug )
    {
        $GLOBALS['eZTemplateDebugInternalsEnabled'] = $debug;
    }

    /// Associative array of resource objects
    var $Resources;
    /// Reference to the default resource object
    var $DefaultResource;
    /// The original template text
    var $Text;
    /// Included texts, usually performed by custom functions
    var $IncludeText;
    /// Included outputs, usually performed by custom functions
    var $IncludeOutput;
    /// The timestamp of the template when it was last modified
    var $TimeStamp;
    /// The left delimiter used for parsing
    var $LDelim;
    /// The right delimiter used for parsing
    var $RDelim;

    /// The resulting object tree of the template
    var $Tree;
    /// An associative array of template variables
    var $Variables;
    /// An associative array of operators
    var $Operators;
    /// An associative array of functions
    var $Functions;
    /// An associative array of function attributes
    var $FunctionAttributes;
    /// An associative array of literal tags
    var $Literals;
    /// True if output details is to be shown
    var $ShowDetails = false;

    var $AutoloadPathList;

    var $CurrentRelatedResource;
    var $CurrentRelatedTemplateName;
}

?>
