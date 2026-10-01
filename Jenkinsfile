pipeline {
    agent any

    options {
        timestamps()
        disableConcurrentBuilds()
        timeout(time: 20, unit: 'MINUTES')
    }

    environment {
        WEB_DIR          = "/var/www/html"
        BACKUP_DIR       = "/var/backups/myapp"
        BACKUP_CURRENT   = "/var/backups/myapp/current"
        PYTHON           = "/opt/selenium-venv/bin/python"
        GITHUB_BRANCH    = "main"
        CURRENT_COMMIT   = ""
        DEPLOYED         = "false"
        SKIP_PIPELINE    = "false"
        GOOD_COMMIT_FILE = "/var/backups/myapp/known_good_commit"
    }

    stages {

        stage('Checkout') {
            steps {
                git(
                    url: 'https://github.com/calvinjohnplacio/debiandevs.git',
                    branch: 'main',
                    credentialsId: 'github-pat'
                )

                script {
                    env.CURRENT_COMMIT = sh(
                        script: 'git rev-parse HEAD',
                        returnStdout: true
                    ).trim()
                }

                sh '''
                    set -e

                    echo "========================================"
                    echo "CHECKOUT"
                    echo "========================================"
                    echo "Commit: $(git rev-parse HEAD)"
                    echo "Commit message: $(git log -1 --pretty=%B)"
                    echo "Repository files:"

                    find . \
                        -type f \
                        -not -path './.git/*' \
                        | sort
                '''
            }
        }

        stage('Check Automatic Rollback') {
            steps {
                script {
                    def message = sh(
                        script: 'git log -1 --pretty=%B',
                        returnStdout: true
                    ).trim()

                    if (message.startsWith('Jenkins rollback:')) {
                        echo '''
========================================
AUTOMATIC ROLLBACK COMMIT DETECTED
========================================
No deployment. No second rollback.
'''
                        env.SKIP_PIPELINE = "true"
                    }
                }
            }
        }

        stage('Check All PHP Syntax') {
            when {
                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {
                sh '''
                    set -e

                    echo "========================================"
                    echo "CHECKING ALL PHP FILES"
                    echo "========================================"

                    PHP_LIST="${WORKSPACE}/php_files.txt"

                    rm -f "${PHP_LIST}"

                    find "${WORKSPACE}" \
                        -type f \
                        -name "*.php" \
                        -not -path "${WORKSPACE}/.git/*" \
                        -not -path "${WORKSPACE}/vendor/*" \
                        -not -path "${WORKSPACE}@tmp/*" \
                        -print > "${PHP_LIST}"

                    PHP_COUNT=$(wc -l < "${PHP_LIST}")

                    echo "PHP files found: ${PHP_COUNT}"

                    if [ "${PHP_COUNT}" -eq 0 ]; then
                        echo "No PHP files found."
                    else
                        PHP_FAILED=0

                        while IFS= read -r PHP_FILE; do
                            echo "Checking: ${PHP_FILE}"

                            if php -l "${PHP_FILE}"; then
                                echo "PASS: ${PHP_FILE}"
                            else
                                echo "FAIL: ${PHP_FILE}"
                                PHP_FAILED=1
                            fi
                        done < "${PHP_LIST}"

                        if [ "${PHP_FAILED}" -ne 0 ]; then
                            echo "========================================"
                            echo "PHP SYNTAX CHECK FAILED"
                            echo "========================================"
                            echo "Server and GitHub were NOT changed."
                            exit 1
                        fi
                    fi

                    echo "========================================"
                    echo "ALL PHP FILES PASSED"
                    echo "========================================"
                '''
            }
        }

        stage('Check Selenium Environment') {
            when {
                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {
                sh '''
                    set -e

                    echo "========================================"
                    echo "CHECKING SELENIUM ENVIRONMENT"
                    echo "========================================"

                    "${PYTHON}" --version

                    "${PYTHON}" -c \
                        "import selenium; print('Selenium:', selenium.__version__)"

                    chromium --version

                    echo "SELENIUM ENVIRONMENT OK"
                '''
            }
        }

        stage('Backup Current Website') {
            when {
                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {
                sh '''
                    set -e

                    echo "========================================"
                    echo "BACKING UP CURRENT WEBSITE"
                    echo "========================================"

                    sudo mkdir -p "${BACKUP_DIR}/new"

                    sudo rm -rf "${BACKUP_DIR}/new"

                    sudo mkdir -p "${BACKUP_DIR}/new"

                    sudo rsync -a \
                        "${WEB_DIR}/" \
                        "${BACKUP_DIR}/new/"

                    sudo rm -rf "${BACKUP_CURRENT}"

                    sudo mv \
                        "${BACKUP_DIR}/new" \
                        "${BACKUP_CURRENT}"

                    echo "BACKUP COMPLETED"
                '''
            }
        }

        stage('Deploy Entire Repository') {
            when {
                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {
                script {
                    env.DEPLOYED = "true"
                }

                sh '''
                    set -e

                    echo "========================================"
                    echo "DEPLOYING ENTIRE REPOSITORY"
                    echo "========================================"

                    sudo rsync -a \
                        --delete \
                        --exclude=".git" \
                        --exclude="Jenkinsfile" \
                        --exclude="tests" \
                        --exclude="vendor" \
                        "${WORKSPACE}/" \
                        "${WEB_DIR}/"

                    echo "ENTIRE REPOSITORY DEPLOYED"
                '''
            }
        }

        stage('HTTP Test') {
            when {
                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {
                sh '''
                    set -e

                    echo "========================================"
                    echo "HTTP TEST"
                    echo "========================================"

                    sleep 2

                    HTTP_CODE=$(curl \
                        --output /dev/null \
                        --silent \
                        --show-error \
                        --write-out "%{http_code}" \
                        http://127.0.0.1/)

                    echo "HTTP status: ${HTTP_CODE}"

                    if [ "${HTTP_CODE}" -lt 200 ] || [ "${HTTP_CODE}" -ge 400 ]; then
                        echo "HTTP TEST FAILED"
                        exit 1
                    fi

                    echo "HTTP TEST PASSED"
                '''
            }
        }

        stage('Python Selenium Test') {
            when {
                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {
                sh '''
                    set -e

                    echo "========================================"
                    echo "SELENIUM TEST"
                    echo "========================================"

                    "${PYTHON}" "${WORKSPACE}/tests/selenium_test.py"

                    echo "SELENIUM TEST PASSED"
                '''
            }
        }

        stage('Mark Version Good') {
            when {
                expression {
                    env.SKIP_PIPELINE != "true"
                }
            }

            steps {
                sh '''
                    set -e

                    echo "========================================"
                    echo "MARKING VERSION AS KNOWN GOOD"
                    echo "========================================"

                    sudo mkdir -p "${BACKUP_DIR}"

                    echo "${CURRENT_COMMIT}" \
                        | sudo tee "${GOOD_COMMIT_FILE}" > /dev/null

                    echo "Known-good commit: ${CURRENT_COMMIT}"
                    echo "VERSION MARKED AS KNOWN GOOD"
                '''
            }
        }
    }

    post {

        success {
            script {
                if (env.SKIP_PIPELINE == "true") {
                    echo '''
========================================
AUTOMATIC ROLLBACK COMMIT
========================================
No deployment performed.
'''
                } else {
                    echo '''
========================================
DEPLOYMENT SUCCESSFUL
========================================
PHP:      PASS
HTTP:     PASS
SELENIUM: PASS

Entire repository deployed.
Current version is known-good.
'''
                }
            }
        }

        failure {
            script {

                if (env.SKIP_PIPELINE == "true") {

                    echo '''
========================================
ROLLBACK COMMIT
========================================
Rollback loop prevented.
'''

                } else if (env.DEPLOYED == "false") {

                    echo '''
========================================
VALIDATION FAILED BEFORE DEPLOYMENT
========================================
The repository failed validation.
Server was NOT changed.
GitHub was NOT changed.
Fix the code and push again.
'''

                } else {

                    echo '''
========================================
DEPLOYMENT FAILED
========================================
Rolling back website...
'''

                    sh '''
                        set +e

                        if [ -d "${BACKUP_CURRENT}" ]; then

                            sudo rsync -a \
                                --delete \
                                "${BACKUP_CURRENT}/" \
                                "${WEB_DIR}/"

                            echo "WEBSITE ROLLBACK COMPLETED."

                        else
                            echo "NO WEBSITE BACKUP FOUND."
                        fi
                    '''

                    def goodCommit = sh(
                        script: '''
                            if [ -f "${GOOD_COMMIT_FILE}" ]; then
                                sudo cat "${GOOD_COMMIT_FILE}"
                            fi
                        ''',
                        returnStdout: true
                    ).trim()

                    if (goodCommit == "") {

                        echo '''
========================================
NO KNOWN-GOOD COMMIT
========================================
GitHub cannot be automatically restored.
'''

                    } else if (goodCommit == env.CURRENT_COMMIT) {

                        echo '''
========================================
CURRENT COMMIT IS KNOWN GOOD
========================================
No GitHub rollback required.
'''

                    } else {

                        sh """#!/bin/bash
                            set -e

                            cd "${WORKSPACE}"

                            echo "========================================"
                            echo "GITHUB ROLLBACK"
                            echo "========================================"

                            git fetch origin "${env.GITHUB_BRANCH}"

                            REMOTE_COMMIT=\$(git rev-parse "origin/${env.GITHUB_BRANCH}")

                            echo "Remote commit: \${REMOTE_COMMIT}"
                            echo "Failed commit: ${env.CURRENT_COMMIT}"

                            if [ "\${REMOTE_COMMIT}" != "${env.CURRENT_COMMIT}" ]; then
                                echo "GitHub changed after Jenkins checkout. Rollback cancelled."
                                exit 1
                            fi

                            echo "Known-good commit: ${goodCommit}"

                            git cat-file -e "${goodCommit}^{commit}"

                            git config user.name "Jenkins"
                            git config user.email "jenkins@localhost"

                            git reset --hard "${goodCommit}"

                            git commit \
                                --allow-empty \
                                -m "Jenkins rollback: ${env.CURRENT_COMMIT}"

                            echo "Pushing known-good version to GitHub..."

                            git push \
                                origin \
                                "HEAD:${env.GITHUB_BRANCH}"

                            echo "========================================"
                            echo "GITHUB ROLLBACK SUCCESSFUL"
                            echo "========================================"
                        """
                    }
                }
            }
        }

        always {
            echo '''
========================================
JENKINS PIPELINE FINISHED
========================================
'''
        }
    }
}
