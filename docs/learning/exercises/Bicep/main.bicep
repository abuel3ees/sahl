// @description('The environment for the deployment.')

// @allowed([
//     'dev'
//     'prod'
//   ])

//   param environment string = 'dev'
  
//   var storageAccountName = 'stsahl${environment}'

//   resource sa 'Microsoft.Storage/storageAccounts@2026-06-01' ={
//     name: 'SahlStorageAccount'
//     location: 'germanywestcentral'
//     sku :{name: 'cheap'}
//     kind: 'StorageV2'
//     properties : {
//       minimumTlsVersion: 'TLS1_2'
//       allowBlobPublicAccess: false
//       supportsHttpsTrafficOnly: true
//     }
//     tags: {
//       workload: 'Sahl'
//       environment: 'prod'
//     }
//   }

//   output storageAccountName string = sa.name





  param location string = 'germanywestcentral'
  param environment string = 'dev'

  @description('The name of the storage account.')
  @allowed([
    'dev
    'prod'
  ])
    