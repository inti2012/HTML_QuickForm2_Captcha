<?php
/**
 * HTML_QuickForm2 package.
 *
 * PHP version 5
 *
 * @category HTML
 * @package  HTML_QuickForm2
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://opensource.org/licenses/bsd-license.php New BSD License
 * @version  SVN: $Id: InputText.php 294057 2010-01-26 21:10:28Z avb $
 * @link     http://pear.php.net/package/HTML_QuickForm2
 */

require_once 'HTML/QuickForm2/Element/InputText.php';

/**
 * Captcha element or QuickForm2:
 * Completely Automated Public Turing test to tell Computers and Humans Apart.
 * Used as anti-spam measure.
 *
 * Features:
 * - Support for different CAPTCHA types:
 *   - numeric captchas (solve mathematical equations)
 *   - ReCAPTCHA (online service to scan books)
 *   - Figlet (ASCII art)
 * - Multiple forms on the same page may have captcha elements
 *   with the same name
 * - Once a captcha in a form is solved, it stays that way until
 *   the form is valid. No need to re-solve a captcha because you
 *   forgot a required field!
 * - Stable captcha: Question stays the same if you do not solve it
 *   correctly the first time
 * - Customizable status messages i.e. when captcha is solved
 *
 * When the form is valid and accepted, use clearCaptchaSession()
 * to destroy the captcha question and answer. Otherwise the
 * form catpcha is seend as already solved for the user.
 *
 * @category HTML
 * @package  HTML_QuickForm2
 * @author   Christian Weiske <cweiske@php.net>
 * @license  http://opensource.org/licenses/bsd-license.php New BSD License
 * @link     http://pear.php.net/package/HTML_QuickForm2
 *
 * @FIXME/@TODO
 * - session storage adapter?
 * - support for recaptcha, normal captcha, figlet
 * - frozen HTML
 * - clear session when form is valid / destroy captcha
 */
abstract class HTML_QuickForm2_Element_Captcha
    extends HTML_QuickForm2_Element_Input
{
    /**
     * Array of input element attributes, with some predefined values
     *
     * @var array
     */
    protected $attributes = array('size' => 5);

    /**
     * Prefix for session variable used to store captcha
     * settings in.
     *
     * @var string
     */
    protected $sessionPrefix = '_qf2_captcha_';

    /**
     * Captcha question. Automatically stored in session
     * to make sure the user gets the same captcha every time.
     *
     * @var string
     */
    protected $capQuestion = null;

    /**
     * Answer to the captcha question.
     * The user must input this value.
     *
     * @var string
     */
    protected $capAnswer = null;

    /**
     * If the captcha has been solved yet.
     *
     * @var boolean
     */
    protected $capSolved = false;

    /**
     * If the captcha has been generated and initialized already
     *
     * @var boolean
     */
    protected $capGenerated = false;



    /**
     * Create new instance.
     *
     * Captcha-specific data attributes:
     * - captchaSolved        - Text to show when the Captcha has been
     *                          solved
     * - captchaSolutionWrong - Error message to show when the captcha
     *                          solution entered by the user is wrong
     * - captchaRender        - Boolean to determine if the captcha itself
     *                          is to be rendered with the solution
     *                          input element
     *
     * @param string $name       Element name
     * @param mixed  $attributes Attributes (either a string or an array)
     * @param array  $data       Element data (special captcha settings)
     */
    public function __construct($name = null, $attributes = null, $data = null)
    {
        //we fill the class data array before it gets merged with $data
        $this->data['captchaSolutionWrong']  = 'Captcha solution is wrong';
        $this->data['captchaSolved']         = 'Captcha already solved';
        $this->data['captchaRender']         = true;
        $this->data['captchaHtmlAttributes'] = array(
            'class' => 'qf2-captcha-question'
        );

        parent::__construct($name, $attributes, $data);
    }



    /**
     * Generates the captcha question and answer and prepares the
     * session data.
     *
     * @return void
     *
     * @throws HTML_QuickForm2_Exception When the session is not started yet
     */
    protected function generateCaptcha()
    {
        if (session_id() == '') {
            //Session has not been started yet. That's not acceptable
            // and breaks captcha answer storage
            throw new HTML_QuickForm2_Exception(
                'Session must be started'
            );
        }

        $this->capGenerated = true;
        $varname = $this->getSessionVarName();
        if (isset($_SESSION[$varname])) {
            //data exist already, use them
            $this->capQuestion
                = $_SESSION[$varname]['question'];
            $this->capAnswer
                = $_SESSION[$varname]['answer'];
            $this->capSolved
                = $_SESSION[$varname]['solved'];
             return;
        }

        list(
            $this->capQuestion,
            $this->capAnswer
        ) = $this->generateCaptchaQA();

        $this->capSolved   = false;
        $_SESSION[$varname] = array(
            'question' => $this->capQuestion,
            'answer'   => $this->capAnswer,
            'solved'   => $this->capSolved
        );
    }



    /**
     * Returns an array with captcha question and captcha answer
     *
     * @return array Array with first value the captcha question
     *               and the second one the captcha answer.
     */
    abstract protected function generateCaptchaQA();



    /**
     * Returns the name to use for the session variable.
     * We include the element's ID to make sure we can use several
     * captcha elements in one form.
     * Also, the container IDs are included to make sure we can use
     * the same element in different forms.
     *
     * @return string Session variable name
     */
    protected function getSessionVarName()
    {
        $el     = $this;
        $idpath = '';
        do {
            $idpath .= '-' . $el->getId();
        } while ($el = $el->getContainer());

        return $this->sessionPrefix
            . $idpath
            . '-data';
    }



    /**
     * Checks if the captcha is solved now.
     * Uses $capSolved variable or user input, which is compared
     * with the pre-set correct answer in $capAnswer.
     *
     * Calls generateCaptcha() if it has not been called before.
     *
     * In case user solution and answer match, a session variable
     * is set so that the captcha is seen as completed across
     * form submissions.
     *
     * @uses $capSolved
     * @uses $capAnswer
     * @uses $capGenerated
     * @uses generateCaptcha()
     *
     * @return boolean True if the captcha is solved
     */
    protected function verifyCaptcha()
    {
        if (!$this->capGenerated) {
            $this->generateCaptcha();
        }

        if ($this->capSolved === true) {
            return true;
        }

        $userSolution = $this->getValue();
        if ($this->capAnswer === null) {
            //no captcha answer?
            return false;
        } else if ($this->capAnswer != $userSolution) {
            return false;
        } else {
            $_SESSION[$this->getSessionVarName()]['solved'] = true;
            return true;
        }
    }



    /**
     * Destroys all captcha session data, so that the previously solved
     * captcha re-appears as unsolved. Question and answers are discarded
     * as well.
     *
     * @return void
     */
    public function clearCaptchaSession()
    {
        $varname = $this->getSessionVarName();
        if (isset($_SESSION[$varname])) {
            unset($_SESSION[$varname]);
        }
    }



    /**
     * Performs the server-side validation.
     * Checks captcha validation first, continues with
     * defined rules if captcha is valid
     *
     * @return boolean Whether the element is valid
     */
    protected function validate()
    {
        //alternative: use custom rule to get error messages
        if (!$this->verifyCaptcha()) {
            $this->setError(
                $this->data['captchaSolutionWrong']
            );
            return false;
        }
        return parent::validate();
    }



    /**
     * Returns the CAPTCHA type.
     *
     * @return string captcha type
     */
    public function getType()
    {
        return 'captcha';
    }



    /**
     * Sets the input value
     *
     * @param string $value Input value
     *
     * @return void
     */
    public function setValue($value)
    {
        $this->setAttribute('value', $value);
        return $this;
    }



    /**
     * Returns the captcha answer input element value.
     * No value (null) when the element is disabled.
     *
     * @return string Input value
     */
    public function getValue()
    {
        return $this->getAttribute('disabled')
            ? null
            : $this->getAttribute('value');
    }



    /**
     * Renders the captcha into a HTML string
     *
     * @see getCaptchaHtml()
     * @see $data['captchaRender']
     * @see $data['captchaSolved']
     *
     * @return string HTML
     */
    public function __toString()
    {
        if ($this->frozen) {
            //FIXME
            return 'captcha!';
        } else {
            if ($this->verifyCaptcha()) {
                return $this->data['captchaSolved'];
            } else {
                $prefix = '';
                if ($this->data['captchaRender']) {
                    $prefix = $this->getCaptchaHtml();
                }
                return $prefix
                    . '<input' . $this->getAttributes(true) . ' />';
            }
        }
    }



    /**
     * Returns the HTML for the captcha itself (question).
     * Used in __toString() and to be used when $data['captchaRender']
     * is set to false.
     *
     * Uses $data['captchaHtmlAttributes'].
     *
     * @return string HTML code
     */
    public function getCaptchaHtml()
    {
        return '<div'
            . self::getAttributesString(
                $this->data['captchaHtmlAttributes']
            ) . '>'
            . $this->capQuestion
            . '</div>';
    }

}

?>