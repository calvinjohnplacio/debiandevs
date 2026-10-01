pipeline {

    agent any

    options {

        timestamps()

        disableConcurrentBuilds()

        timeout(
            time: 20,
            unit: 'MINUTES'
        )
    }

    environment {

        WEB_DIR = "/var/www/html"

        BACKUP_DIR = "/var/backups/myapp"

        PYTHON = "/opt/selenium-venv/bin/python"

        DEPLOYED = "false"
    }


    stages {


        /*
         * ==========================================
         * CHECKOUT FROM GITHUB
         * ==========================================
         */

        stage('Checkout') {

            steps {

                git(
                    url: 'https://github.com/calvinjohnplacio/debiandevs.git',
                    branch: 'main',
                    credentialsId: 'github-pat'
                )

                sh '''
                    echo "================================"
                    echo "CHECKOUT"
                    echo "================================"

                    echo "Git commit:"
                    git rev-parse HEAD

                    echo ""
                    echo "Git branch:"
                    git branch --show-current

                    echo ""
                    echo "Files:"
                    find . -maxdepth 2 -type f | sort
                '''
            }
        }


        /*
         * ==========================================
         * CHECK ALL PHP FILES
         * ==========================================
         *
         * IMPORTANT:
         *
         * This happens BEFORE backup and deployment.
         *
         * If PHP has a syntax error:
         *
         * Jenkins stops.
         * /var/www/html is NOT changed.
         *
         */

        stage('Check PHP Syntax') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "CHECKING ALL PHP FILES"
                    echo "================================"

                    PHP_COUNT=$(find "${WORKSPACE}" \
                        -type f \
                        -name "*.php" \
                        -not -path "${WORKSPACE}/vendor/*" \
                        -not -path "${WORKSPACE}@tmp/*" \
                        | wc -l)

                    echo ""
                    echo "PHP files found: ${PHP_COUNT}"

                    if [ "${PHP_COUNT}" -eq 0 ]; then

                        echo ""
                        echo "No PHP files found."

                    else

                        echo ""
                        echo "Running PHP syntax checks..."
                        echo ""

                        find "${WORKSPACE}" \
                            -type f \
                            -name "*.php" \
                            -not -path "${WORKSPACE}/vendor/*" \
                            -not -path "${WORKSPACE}@tmp/*" \
                            -print0 |
                        xargs -0 -n1 php -l

                    fi

                    echo ""
                    echo "================================"
                    echo "ALL PHP FILES PASSED"
                    echo "================================"
                '''
            }
        }


        /*
         * ==========================================
         * CHECK PYTHON + SELENIUM + CHROMIUM
         * ==========================================
         */

        stage('Check Selenium Environment') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "CHECKING SELENIUM ENVIRONMENT"
                    echo "================================"

                    echo ""
                    echo "Python:"
                    ${PYTHON} --version

                    echo ""
                    echo "Selenium:"
                    ${PYTHON} -c \
                        "import selenium; print(selenium.__version__)"

                    echo ""
                    echo "Chromium:"
                    chromium --version

                    echo ""
                    echo "Selenium environment OK."
                '''
            }
        }


        /*
         * ==========================================
         * BACKUP CURRENT PRODUCTION WEBSITE
         * ==========================================
         */

        stage('Backup Current Website') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "BACKING UP CURRENT WEBSITE"
                    echo "================================"

                    sudo mkdir -p "${BACKUP_DIR}"

                    echo ""
                    echo "Removing old backup..."

                    sudo rm -rf \
                        "${BACKUP_DIR}/current"

                    echo ""
                    echo "Creating new backup directory..."

                    sudo mkdir -p \
                        "${BACKUP_DIR}/current"

                    echo ""
                    echo "Copying:"
                    echo "${WEB_DIR}"
                    echo ""
                    echo "To:"
                    echo "${BACKUP_DIR}/current"

                    sudo rsync -a \
                        "${WEB_DIR}/" \
                        "${BACKUP_DIR}/current/"

                    echo ""
                    echo "================================"
                    echo "BACKUP COMPLETED"
                    echo "================================"
                '''
            }
        }


        /*
         * ==========================================
         * DEPLOY
         * ==========================================
         *
         * DEPLOYED=true is set BEFORE rsync.
         *
         * This means if rsync partially changes
         * /var/www/html and then fails, Jenkins
         * will still attempt rollback.
         *
         */

        stage('Deploy to /var/www/html') {

            steps {

                script {

                    env.DEPLOYED = "true"
                }

                sh '''
                    set -e

                    echo "================================"
                    echo "DEPLOYING"
                    echo "================================"

                    echo ""
                    echo "Source:"
                    echo "${WORKSPACE}/"

                    echo ""
                    echo "Destination:"
                    echo "${WEB_DIR}"

                    echo ""

                    sudo rsync -a \
                        --delete \
                        --exclude=".git" \
                        --exclude="Jenkinsfile" \
                        --exclude="tests" \
                        "${WORKSPACE}/" \
                        "${WEB_DIR}/"

                    echo ""
                    echo "================================"
                    echo "DEPLOYMENT COMPLETED"
                    echo "================================"
                '''
            }
        }


        /*
         * ==========================================
         * BASIC HTTP TEST
         * ==========================================
         */

        stage('HTTP Test') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "HTTP TEST"
                    echo "================================"

                    sleep 2

                    HTTP_CODE=$(curl \
                        --output /dev/null \
                        --silent \
                        --show-error \
                        --write-out "%{http_code}" \
                        http://127.0.0.1/)

                    echo ""
                    echo "HTTP status: ${HTTP_CODE}"

                    if [ "${HTTP_CODE}" -lt 200 ] || [ "${HTTP_CODE}" -ge 400 ]; then

                        echo ""
                        echo "HTTP TEST FAILED."

                        exit 1

                    fi

                    echo ""
                    echo "================================"
                    echo "HTTP TEST PASSED"
                    echo "================================"
                '''
            }
        }


        /*
         * ==========================================
         * SELENIUM TEST
         * ==========================================
         */

        stage('Python Selenium Test') {

            steps {

                sh '''
                    set -e

                    echo "================================"
                    echo "PYTHON SELENIUM TEST"
                    echo "================================"

                    ${PYTHON} \
                        "${WORKSPACE}/tests/selenium_test.py"

                    echo ""
                    echo "================================"
                    echo "SELENIUM TEST PASSED"
                    echo "================================"
                '''
            }
        }
    }


    /*
     * ==========================================
     * POST ACTIONS
     * ==========================================
     */

    post {


        /*
         * ========================================
         * SUCCESS
         * ========================================
         */

        success {

            echo '''
========================================
DEPLOYMENT SUCCESSFUL
========================================
'''

            sh '''
                echo ""
                echo "Current website:"
                echo "================"

                ls -la "${WEB_DIR}"

                echo ""
                echo "Website deployed successfully."

                echo ""
                echo "Backup available at:"
                echo "${BACKUP_DIR}/current"
            '''
        }


        /*
         * ========================================
         * FAILURE
         * ========================================
         */

        failure {

            script {

                if (env.DEPLOYED == "true") {

                    echo '''
========================================
DEPLOYMENT FAILED
========================================
ROLLING BACK TO PREVIOUS VERSION
========================================
'''

                    sh '''
                        set +e

                        echo ""
                        echo "Checking backup..."

                        if [ -d "${BACKUP_DIR}/current" ]; then

                            echo ""
                            echo "Backup found."

                            echo ""
                            echo "Restoring:"
                            echo "${BACKUP_DIR}/current/"

                            echo ""
                            echo "To:"
                            echo "${WEB_DIR}/"

                            sudo rsync -a \
                                --delete \
                                "${BACKUP_DIR}/current/" \
                                "${WEB_DIR}/"

                            ROLLBACK_STATUS=$?

                            echo ""

                            if [ "${ROLLBACK_STATUS}" -eq 0 ]; then

                                echo "================================"
                                echo "ROLLBACK COMPLETED"
                                echo "================================"

                            else

                                echo "================================"
                                echo "ROLLBACK FAILED"
                                echo "================================"

                                exit 1

                            fi

                        else

                            echo "================================"
                            echo "NO BACKUP AVAILABLE"
                            echo "================================"

                            exit 1

                        fi
                    '''

                } else {

                    echo '''
========================================
BUILD FAILED BEFORE DEPLOYMENT
========================================
NO ROLLBACK REQUIRED
========================================

The existing website was not changed.
'''
                }
            }
        }


        /*
         * ========================================
         * ALWAYS
         * ========================================
         */

        always {

            echo '''
========================================
JENKINS BUILD FINISHED
========================================
'''
        }
    }
}
